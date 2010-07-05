<?php

class CommonLogger {
  function __construct($app, $logger = NULL) {
    $this->app = $app;
    $this->logger = $logger;
  }

  function __invoke($env) {
    $this->logger = $this->logger ?: $env['pip.errors'];
    $time = microtime();
    list($status, $headers, $body) = call_user_func($this->app, $env);
    $now = microtime();
    vfprintf($this->logger, "%s - %s [%s] '%s %s%s %s' %d %s %0.4f\n",
      array(
        isset($env['REMOTE_ADDR']) ? $env['REMOTE_ADDR'] : '-',
        isset($env['REMOTE_USER']) ? $env['REMOTE_USER']: '-',
        strftime('%d/%b/%Y %H:%M:%S', $now),
        $env['REQUEST_METHOD'],
        $env['PATH_INFO'],
        empty($env['QUERY_STRING']) ? '' : '?' . $env['QUERY_STRING'],
        $env['HTTP_VERSION'],
        $status,
        $headers['content-length'],
        $now - $time,
      )
    );
    return array($status, $headers, $body); 
  }
} 
