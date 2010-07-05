<?php

include_once 'io.php';
include_once 'logging.php';

use pip\io;
use pip\logging;

$logger = logging\getLogger('root');
$logger->level = logging\DEBUG;

error_reporting(E_ALL);

try {
  $listener = new io\Listener();
  $listener->bind_and_listen();
  logging\info("listening on $listener->address:$listener->port");
  if ($listener->can_accept() and $client = $listener->accept()) {
    if ($client->can_read() and $in = $client->recv()) {
      logging\info("recv'd $in");
      if ($client->can_write()) {
        $client->write("got your message: $in");
      } else {
        logging\info('failed write');
      }
    } else {
      logging\info('failed recv');
    }
  } else {
    logging\info('failed accept()');
  }
} catch (io\SocketError $e) {
  logging\info(get_class($e));
}

$listener->shutdown_and_close();
$client->shutdown_and_close();
