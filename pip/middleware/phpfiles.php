<?php

class PhpFiles {
  function __construct($app) {
    $this->app = $app;
  }

  function __invoke($env) {
    $basename = substr($env['PATH_INFO'], 1);
    $filename = CWD.DIRECTORY_SEPARATOR.($basename == '' ? 'index.html' : $basename);
    return is_file($filename) ?
      $this->_serve($filename) :
      call_user_func($this->app, $env);
  }

  function _serve($filename) {
    ob_start();
    include $filename;
    $buf = ob_get_clean();
    $body = fopen('php://temp', 'w+');
    $len = fwrite($body, $buf);
    $headers['content-length'] = $len;
    $headers['content-type'] = 'text/html';
    fclose($src);
    return array(200, $headers, $body);
  }
}
