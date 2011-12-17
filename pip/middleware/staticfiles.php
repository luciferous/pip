<?php

require_once 'pip/logging.php';

use pip\logging;

class StaticFiles {
  static $mimeTypes = array(
    'css' => 'text/css',
    'js' => 'application/x-javascript',
    'html' => 'text/html',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'jpg' => 'image/jpeg',
    'txt' => 'text/plain',
  );

  function __construct($app, $root = NULL) {
    $this->app = $app;
    $this->root = $root ?: getcwd();
    $this->logger = logging\getLogger('pip');
    $this->logger->info("Serving static files from $this->root");
  }

  function __invoke($env) {
    $basename = substr($env['PATH_INFO'], 1);
    $filename = $this->root
      . DIRECTORY_SEPARATOR
      . ($basename == '' ? 'index.html' : $basename);
    return is_file($filename) ?
      $this->_serveStatic($filename) :
      call_user_func($this->app, $env);
  }

  static function _mimeType($ext) {
    return isset(self::$mimeTypes[$ext]) ?
      self::$mimeTypes[$ext] : 'application/octet-stream';
  }

  function _serveStatic($filename) {
    $body = fopen('php://temp', 'w+');
    if (FALSE === ($src = fopen($filename, 'r'))) {
      return array(404, array(), $body);
    }
    $len = stream_copy_to_stream($src, $body);
    $stat = fstat($src);
    $headers['last-modified'] = gmdate('D, d M Y H:i:s', $stat['mtime']).' GMT';
    $headers['content-length'] = $len;
    $headers['content-type'] = self::_mimeType(
      pathinfo($filename, PATHINFO_EXTENSION));
    fclose($src);
    return array(200, $headers, $body);
  }
}
