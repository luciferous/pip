#!/usr/bin/env php -d include_path=".:.."
<?php

use pip\servers;
use pip\logging;

require_once 'pip/http.php';
require_once 'pip/logging.php';

$logger = pip\logging\getLogger('pip');
$logger->level = logging\DEBUG;

if (!debug_backtrace()) {
  $http = new servers\Http(array('iface' => 'localhost', 'port' => 5000));
  $http->start(function($env) {
    $content = 'Hello, world!';
    $body = fopen('php://temp', 'w+');
    fwrite($body, $content);
    return array(
      200,
      array(
        'content-type' => 'text/plain',
        'content-length' => strlen($content)),
      $body);
  });
}
