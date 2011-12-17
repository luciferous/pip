<?php

namespace pip\webapp;

use pip;
use pip\logging;
use ErrorException;

require_once 'base.php';

class PipApplication {
  function __construct($routes, $options = array()) {
    $this->routes = $routes;
    $this->options = $options;
    foreach ($this->routes as &$route) {
      if (substr($route[0], -2) != '$!') $route[0] .= '$!';
      if (substr($route[0], 0, 1) != '!^') $route[0] = '!^' . $route[0];
    }
  }

  function __invoke($env) {
    $response = new Response();
    foreach ($this->routes as $route) {
      list($pattern, $handler) = $route;
      logging\debug("$pattern <=> {$env['PATH_INFO']}");
      preg_match($pattern, $env['PATH_INFO'], $matches);
      if (empty($matches)) continue;
      $instance = new $handler(new Request($env));
      try {
        call_user_func_array(
          array($instance, strtolower($env['REQUEST_METHOD'])),
          array_slice($matches, 1)
        );
        return $instance->response->build();
      } catch (MethodNotImplemented $e) {
        $response->status = 500;
        $response->headers['content-type'] = 'text/plain';
        fwrite($response->body, $e->getTraceAsString());
      }
    }
    $response->status = 404;
    return $response->build();
  }
}

class Request {
  function __construct($env) {
    $this->environ = $env;
    $this->method = $env['REQUEST_METHOD'];
    $this->path = $env['PATH_INFO'];
    $this->scheme = $env['pip.url_scheme'];
    $this->body = $env['pip.input'];
    $this->query_string = isset($env['QUERY_STRING']) ? $env['QUERY_STRING'] : '';
    $this->GET = array();
    $this->POST = array();

    if (isset($env['QUERY_STRING']) and $qs = $env['QUERY_STRING']) {
      $this->parseDict($this->GET, $qs);
    }

    if (isset($env['CONTENT_TYPE']) and
      'application/x-www-form-urlencoded' == $env['CONTENT_TYPE'])
    {
      rewind($this->body);
      $contents = stream_get_contents($this->body);
      $this->parseDict($this->POST, $contents);
    }
  }

  function get($key, $null = '') {
    return isset($this->GET[$key])
      ? $this->GET[$key]
      : (
        isset($this->POST[$key])
        ? $this->POST[$key]
        : $null
      );
  }

  private function parseDict(&$dict, $qs) {
    logging\debug($qs);
    foreach (explode('&', $qs) as $pair) {
      list($key, $value) = explode('=', $pair);
      $dict[$key] = urldecode($value);
    }
  }
}

class Response {
  public $status, $headers, $out;
  function __construct() {
    $this->status = 200;
    $this->headers = array(
      'content-type' => 'text/html; charset=utf8',
      'content-length' => 0);
    $this->out = fopen('php://temp', 'w+');
  }
  function build() {
    $stat = fstat($this->out);
    $this->headers['content-length'] = $stat[7];
    return array($this->status, $this->headers, $this->out);
  }
}

class NotImplemented extends ErrorException { }

class RequestHandler {
  function __construct($request) {
    $this->response = new Response();
    $this->request = $request;
  }
  function error($status) {
    $this->response->status = $status;
  }
  function get() {
    throw new NotImplemented();
  }
  function post() {
    throw new NotImplemented();
  }
  function head() {
    throw new NotImplemented();
  }
}

