<?php

/**
 * Http server.
 */

namespace pip\servers;

require_once 'base.php';
require_once 'logging.php';
require_once 'io.php';

const SERVER = 'PIP/0.0';
const EXPECT_100_RESPONSE = "HTTP/1.1 100 Continue\r\n\r\n";
const BAD_REQUEST = "HTTP/1.1 400 Bad Request\r\n\r\n";
const INTERNAL_SERVER_ERROR = "HTTP/1.1 500 Internal Server Error\r\n\r\n";
const HTTP_RESPONSE = "HTTP/1.1 %d %s\r\nDate: %s GMT\r\n%s\r\n\r\n";

use pip;
use pip\logging;
use pip\io;
use pip\io\SocketError;
use pip\io\ConnectionClosed;

use ErrorException;

class ParseError extends ErrorException { }

class Http extends pip\base\SocketServer {

  static $phrase = array(
    100 => 'Continue',
    101 => 'Switching Protocols',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    307 => 'Temporary Redirect',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Time-out',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Large',
    415 => 'Unsupported Media Type',
    416 => 'Requested range not satisfiable',
    417 => 'Expectation Failed',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Time-out',
    505 => 'HTTP Version not supported',
  );

  function process_client($sock) {
    try {
      $conn = new io\Connection($sock);
      $env = array(
        'pip.input' => fopen('php://temp', 'w+'),
        'pip.errors' => STDERR,
        'pip.multithread' => TRUE,
        'pip.multiprocess' => TRUE,
        'pip.version' => array(1, 1),
        'pip.url_scheme' => 'http',
      );
      $this->_process_client($conn, $env);
    } catch (ParseError $e) {
      $conn->write(BAD_REQUEST);
      logging\warning($e->getMessage());
    } catch (ConnectionClosed $e) {
      logging\warning($e->getMessage());
    } catch (ErrorException $e) {
      logging\warning($e->getMessage());
      logging\warning($e->getTraceAsString());
    }
    // finally
    $conn->shutdown_and_close();
    fclose($env['pip.input']);
  }

  function _process_client($conn, $env) {
    $buf = '';
    $request = NULL;
    while (is_null($request)) {
      if (!$conn->can_read()) continue;
      $buf .= $conn->recv();
      if (false === ($end = strpos($buf, "\r\n\r\n"))) continue;
      $request = parse_request(substr($buf, 0, $end));
      // Write any extra bytes left over to pip.input
      fwrite($env['pip.input'], substr($buf, $end + 4));
    }
    _init_environment($env, $request);

    $stat = fstat($env['pip.input']);
    $bytes_left = isset($env['CONTENT_LENGTH']) ? $env['CONTENT_LENGTH'] : 0;
    $bytes_left -= $stat[7];
    while ($bytes_left > 0) {
      logging\debug("$bytes_left bytes left");
      if (!$conn->can_read()) continue;
      $buf = $conn->recv();
      $bytes_left -= fwrite($env['pip.input'], $buf);
    }

    $response = $this->call_app($env);
    if (100 == $response[0]) {
      $conn->write(EXPECT_100_RESPONSE);
      unset($env['HTTP_EXPECT']);
      $response = $this->call_app($env);
    }

    list($status, $headers, $body) = $response;
    $headers['connection'] = 'close';
    if ($headers['content-length'] == 0) unset($headers['content-type']);
    if (!isset($headers['last-modified'])) {
      $headers['last-modified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
    }

    $lines = array();
    foreach ($headers as $key => $value) {
      $lines[] = "$key: $value\r\n";
    }

    rewind($body);
    $out = sprintf(HTTP_RESPONSE, $status, Http::$phrase[$status],
      gmdate('D, d M Y H:i:s', time()), implode('', $lines));

    if ($conn->can_write()) $conn->write($out);

    $body_stat = fstat($body);
    if (isset($headers['content-length']) &&
      $remaining = min($headers['content-length'], $body_stat[7])
    ) {
      while ($remaining) {
        logging\debug("remaining: $remaining bytes");
        $remaining -= $conn->write(stream_get_contents($body, io\SNDBUF));
        usleep(1);
      }
    }
    logging\debug('done');
    if (is_resource($body)) fclose($body);
  }
}

function _init_environment(&$env, $request) {
  list($line, $headers) = $request;
  $env['REQUEST_METHOD'] = $line[0];
  $env['HTTP_VERSION'] = $line[2];
  $parts = parse_url($line[1]);
  $env['PATH_INFO'] = isset($parts['path']) ? $parts['path'] : '';
  $env['QUERY_STRING'] = isset($parts['query']) ? $parts['query'] : '';
  $env['SCRIPT_NAME'] = '';

  $env['SERVER_NAME'] = strstr($headers['host'], ':', TRUE);
  $env['SERVER_PORT'] = (int)(strstr($headers['host'], ':') ?: 80);
  unset($headers['host']);
  foreach (array('content-length', 'content-type') as $field) {
    if (isset($headers[$field])) {
      $tr = strtoupper(strtr($field, '-', '_'));
      $env[$tr] = $headers[$field];
      unset($headers[$field]);
    }
  }
  foreach ($headers as $key => $value) {
    $key = strtoupper(strtr($key, '-', '_'));
    $env['HTTP_' . $key] = $value;
  }
}

function parse_request($buf) {
  $lines = explode("\r\n", $buf);
  if (NULL === ($request_line = array_shift($lines))) return;
  $tokens = explode(' ', $request_line);
  if (count($tokens) != 3) throw new ParseError('bad request line');
  if ('HTTP/1.1' != $tokens[2]) throw new ParseError('not http/1.1');

  $headers = array();
  foreach ($lines as $line) {
    if ('' == $line) continue;
    if (preg_match('!^([\w-]+):\s*(\S.*)$!', $line, $matches)) {
      $headers[strtolower($matches[1])] = $matches[2];
    }
    else throw new ParseError('could not parse: ' . $line);
  }
  if (!isset($headers['content-length'])) $headers['content-length'] = 0;
  if (!isset($headers['host'])) throw new ParseError('host required for http/1.1');
  return array($tokens, $headers);
}
