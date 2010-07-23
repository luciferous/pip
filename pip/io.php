<?php

namespace pip\io;

use pip\logging;
use ErrorException;

require_once 'logging.php';

const RECVBUF = 16384;
const SNDBUF = 4096;
const BACKLOG = 1024;

$__all__ = array('ConnectionClosed', 'SocketError', 'Socket', 'select', 'Connection', 'Listener');

class SocketError extends ErrorException { }
class ConnectionClosed extends SocketError { }

class Socket {
  /**
   * Shutdown and close socket.
   */
  function shutdown_and_close($max_retries = 2) {
    $retries = 0;
    socket_shutdown($this->socket, 2);
    retry:
    if (false === socket_close($this->socket)) {
      if ($this->error() == SOCKET_EINTR and
        ++$retries < $max_retries) goto retry;
      $code = $this->error();
      throw new SocketError(socket_strerror($code), $code);
    }
  }

  /**
   * Convenience method for returning the last error.
   */
  function error($usethis = true) {
    return socket_last_error($usethis ? $this->socket : NULL);
  }
}

/**
 * The listening socket. 
 */
class Listener extends Socket {

  public $address;
  public $port;

  /**
   * Wrapper for socket_create().
   */
  function __construct() {
    $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (false === $this->socket) {
      $code = $this->error();
      throw new SocketError(socket_strerror($code), $code);
    }
  }

  /**
   * Sets options, binds, and listens.
   */
  function bind_and_listen($iface = '127.0.0.1', $port = 5000) {
    if (false === (
      socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1) and 
      socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1)
    )) logging\warning('Failed to set some socket options.');
    if (false === (
      socket_bind($this->socket, $iface, $port) and
      socket_listen($this->socket, BACKLOG)
    )) {
      $code = $this->error();
      throw new SocketError(socket_strerror($code), $code);
    }
    socket_getsockname($this->socket, $this->address, $this->port);
  }

  /**
   * Waits until an accept won't block.
   */
  function can_accept($timeout = NULL) {
    list($reads, $_, $_) = _retrying_select(
      SOCKET_EAGAIN,  array($this->socket), NULL, NULL, $timeout);
    return in_array($this->socket, $reads);
  }

  /**
   * Accepts a connection.
   */
  function accept() {
    $client = socket_accept($this->socket);
    if (false === $client) {
      $code = $this->error();
      throw new SocketError(socket_strerror($code), $code);
    }
    return new Connection($client);
  }
}


/**
 * Socket representing a connection.
 */
class Connection extends Socket {
  function __construct($socket) {
    $this->socket = $socket;
  }

  /**
   * Convenience method to select() this socket for writing.
   */
  function can_write($timeout = NULL) {
    list($_, $writes, $_) = _retrying_select(
      SOCKET_EAGAIN, NULL, array($this->socket), NULL, $timeout);
    return in_array($this->socket, $writes);
  }

  /**
   * Convenience method to select() this socket for reading.
   */
  function can_read($timeout = NULL) {
    list($reads, $_, $_) = _retrying_select(
      SOCKET_EAGAIN, array($this->socket), NULL, NULL, $timeout);
    return in_array($this->socket, $reads);
  }

  /**
   * Wrapper for socket_recv().
   */
  function recv($len = RECVBUF, $flags = NULL) {
    $bytes = socket_recv($this->socket, $data, $len, $flags);
    if (0 === $bytes) throw new ConnectionClosed();
    if (false === $bytes) {
      $code = $this->error();
      throw new SocketError(socket_strerror($code), $code);
    }
    return $data;
  }

  /**
   * Wrapper for socket_write().
   */
  function write($str) {
    $total = 0;
    while ($str != '') {
      $len = socket_write($this->socket, $str);
      if (false === $len) {
        $error = $this->error();
        throw new SocketError(socket_strerror($code), $code);
      }
      $str = substr($str, $len);
      $total += $len;
    }
    return $total;
  }
}

/**
 * Wrapper for socket_select().
 */
function select(array $reads = NULL, array $writes = NULL, array $errors = NULL,
  $timeout = NULL, $usec = NULL
) {
  $reads = $reads ?: array();
  $writes = $writes ?: array();
  $errors = $errors ?: array();
  $changed = socket_select($reads, $writes, $errors, $timeout, $usec);
  if (false === $changed) {
    $code = socket_last_error();
    throw new SocketError(socket_strerror($code), $code);
  } else {
    return array($reads, $writes, $errors);
  }
}

/**
 * select() that retries if interrupted by the specified error constant.
 */
function _retrying_select($codes = array(SOCKET_EAGAIN), array $reads = NULL,
  array $writes = NULL, array $errors = NULL, $timeout = NULL, $usec = NULL
) {
  if (is_scalar($codes)) $codes = array($codes);
  retry:
  try {
    return select($reads, $writes, $errors, $timeout, $usec);
  } catch (SocketError $e) {
    if (in_array($e->getCode(), $codes)) {
      logging\error('caught ' . _const_name($code) . '... retrying select()');
      goto retry;
    }
    throw $e;
  }
}

/**
 * Returns the name of the socket error constant.
 */
function _const_name($code) {
  static $names = array(
    SOCKET_EAGAIN => 'EAGAIN',
    SOCKET_EBADF => 'EBADF',
    SOCKET_EINTR => 'EINTR',
    SOCKET_ECONNABORT => 'ECONNABORT',
  );
  return isset($names[$code]) ? $names[$code] : "<code:$code>";
}
