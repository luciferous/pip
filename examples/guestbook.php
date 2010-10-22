<?php

/**
 * To run: php -d include_path=".:.." <filename.php>
 */

use pip\webapp;
use pip\servers;
use pip\logging;
use pip\webapp\template;

require_once 'pip/http.php';
require_once 'pip/webapp.php';
require_once 'pip/logging.php';
require_once 'pip/template.php';

class Guestbook extends webapp\RequestHandler {
  function get() {
    fwrite(
      $this->response->out,
      template\render(dirname(__FILE__) . '/templates/guest.tpl.php')
    );
  }

  function post() {
    fwrite(
      $this->response->out,
      template\render(
        dirname(__FILE__) . '/templates/guest-signed.tpl.php',
        array(
          'from' => $this->request->get('from', 'Anonymous Coward'),
          'message' => $this->request->get('message', '')
        )
      )
    );
  }
}

$app = new webapp\PipApplication(array(
    array('/', 'Guestbook'),
  ),
  array('debug' => TRUE)
);

if (count(debug_backtrace()) == 0) {
  $logger = logging\getLogger('pip');
  $logger->level = logging\DEBUG;
  $http = new servers\Http(array(
    'iface' => 'localhost',
    'port' => 5000,
    'workers' => 4,
    'timeout' => 5));

  // Show off some middleware
  require_once 'pip/middleware/commonlogger.php';
  //$http->apps[] = 'CommonLogger';
  require_once 'pip/middleware/staticfiles.php';
  //$http->apps[] = array('StaticFiles', getcwd() . '/public');

  $http->start($app);
}
