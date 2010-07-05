<?php

/**
 * Basic logging module.
 *
 * Author: Neuman Vong <neuman+pip@0xa000.org>
 */

namespace pip\logging;

const DEBUG = 0;
const INFO = 1;
const WARNING = 2;
const ERROR = 3;
const CRITICAL = 4;

class LoggingError extends \ErrorException { };

class StreamHandler {
  function __construct($out = STDERR) {
    $this->out = $out;
  }
  function __invoke($data) {
    fwrite($this->out, $data);
    fflush($this->out);
  }
}

class Logger {
  static $root;
  public $handlers = array();
  function __construct($name, $handler, $level = INFO) {
    $this->name = empty($name) ? 'root' : $name;
    $this->handlers[] = $handler;
    $this->level = $level;
  }
  function __call($name, $arguments) {
    $name = _remove_ns_prefix($name);
    if ($this->level > _level_code($name)) return;
    $message = current($arguments);
    foreach($this->handlers as $handler) {
      $trace = debug_backtrace();
      while ($frame = next($trace)) {
        if ($frame['file'] != __FILE__) break;
      }
      call_user_func($handler,
        sprintf(
          "[%s:%s] %s in %s on line %s\n",
          strtoupper($name),
          $this->name,
          is_string($message) ? $message : print_r($message, TRUE),
          basename($frame['file']),
          $frame['line']
        )
      );
    }
  }
}

Logger::$root = new Logger('root', new StreamHandler());

function basicConfig($level = INFO) {
  $logger = Logger::$root;
  $logger->level = $level; 
}

function getLogger($name) {
  static $loggers = array();
  if (empty($name)) $name = 'root';
  if ($name == 'root') return Logger::$root;
  return isset($loggers[$name]) ?
    $loggers[$name] :
    $loggers[$name] = new Logger($name, new StreamHandler());
}

function info($message) { _log(__FUNCTION__, $message); }
function debug($message) { _log(__FUNCTION__, $message); }
function warning($message) { _log(__FUNCTION__, $message); } 
function error($message) { _log(__FUNCTION__, $message); }
function critical($message) { _log(__FUNCTION__, $message); }

// Private

function _level_code($name) {
  static $map = array(
    'debug' => DEBUG,
    'info' => INFO,
    'warning' => WARNING,
    'error' => ERROR,
    'critical' => CRITICAL,
  );
  $name = _remove_ns_prefix($name);
  if (isset($map[$name])) return $map[$name];
  else throw new LoggingError('invalid name: ' . $name);
}

function _log($name, $message) {
  $logger = Logger::$root;
  $logger->$name($message);
}

function _remove_ns_prefix($name) {
  return preg_replace('/^' . preg_quote(__NAMESPACE__ . '\\') . '/', '', $name);
}
