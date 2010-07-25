<?php

namespace pip\base;

require_once 'io.php';
require_once 'logging.php';

use pip\logging;
use pip\io;
use pip\io\SocketError;

use ErrorException;
use stdClass;

class SocketServer extends io\Listener {
  static $defaults = array(
    'workers' => 1,
    'timeout' => 30,
    'iface' => '0.0.0.0',
    'port' => 5000,
  );

  function __construct($options = array()) {
    $this->apps = array();
    $this->workers = array();
    $this->pipe = array();
    $this->sigqueue = array();
    $this->opt = array_replace(self::$defaults, $options);
    $this->logger = logging\getLogger('pip');
    parent::__construct();
  }

  function handle_signal($sig) {
    $this->logger->debug("queued signal $sig");
    $this->sigqueue[] = $sig;
    socket_write($this->pipe[0], '.', 1);
  }

  function master_loop() {
    _init_pipe($this);
    $this->bind_and_listen($this->opt['iface'], $this->opt['port']);
    $this->logger->info("listening on $this->address:$this->port");

    $done = FALSE;
    _init_signals($this);

    $this->maintain_worker_count();
    $last_check = time();
    while (!$done) {
      pcntl_signal_dispatch();
      $this->reap_all_workers();
      switch (array_shift($this->sigqueue)) {
      case NULL:
        if (($last_check + $this->opt['timeout']) >= ($last_check = time())) {
          $this->murder_lazy_workers();
        }
        else {
          $this->master_sleep($this->opt['timeout'] / 2 + 1);
        }
        $this->maintain_worker_count();
        $this->master_sleep(1);
        break;
      case SIGTTIN:
        $this->opt['workers'] += 1;
        break;
      case SIGTTOU:
        if ($this->opt['workers'] > 1) $this->opt['workers'] -= - 1;
        break;
      case SIGQUIT:
      case SIGINT:
        $this->logger->debug("caught signal");
        $done = TRUE;
        break;
      }
    }
    $this->stop();
  }

  function master_sleep($sec) {
    $reads = array($this->pipe[1]);
    //$this->logger->debug("sleep for {$sec}s");
    if (FALSE === ($number = @socket_select($reads, $w, $e, $sec))) {
      $err = socket_last_error();
      $this->logger->error(socket_strerror($err));
      if ($err == SOCKET_EAGAIN or $err == SOCKET_EINTR) return;
      throw new SocketError(socket_strerror($err));
    }
    if (count($reads) == 0) return;
    $first = current($reads);
    while (TRUE) {
      if (FALSE === @socket_recv($first, $data, io\RECVBUF, MSG_DONTWAIT)) {
        $err = socket_last_error($first);
        $this->logger->debug(socket_strerror($err));
        if ($err === SOCKET_EAGAIN or $err === SOCKET_EINTR) break;
        throw new SocketError(socket_strerror($err));
      }
    }
  }

  function stop($graceful = TRUE) {
    $limit = time() + $this->opt['timeout'];
    while (count($this->workers)) {
      if (time() > $limit) break;
      $this->kill_each_worker($graceful ? SIGQUIT : SIGTERM);
      sleep(0.1);
      $this->reap_all_workers();
    }
    $this->kill_each_worker(SIGKILL);
    socket_close($this->socket);
    $this->logger->debug('closed server socket');
  }

  function worker_close_pipe() {
    socket_close($this->pipe[0]);
  }

  function worker_quit() {
    $this->alive = NULL;
    socket_close($this->socket);
  }

  function worker_loop($worker) {
    $this->pid = posix_getpid();
    _init_pipe($this);
    $this->logger->debug('worker alive');

    $this->alive = $worker->tmp;
    socket_set_nonblock($this->socket);

    pcntl_signal(SIGTERM, function() { exit; });
    pcntl_signal(SIGINT, function() { /* do nothing */ });
    pcntl_signal(SIGUSR1, array($this, 'worker_close_pipe'));
    pcntl_signal(SIGQUIT, array($this, 'worker_quit'));

    while (TRUE) {
      pcntl_signal_dispatch();
      if (is_null($this->alive)) break;
      ftruncate($this->alive, 0);
      $read = array($this->socket);
      $error = $this->pipe;
      try {
        if (FALSE === (
          $number = @socket_select($read, $_, $error, $this->opt['timeout'])))
        {
          $err = socket_last_error($this->socket);
          if ($err != 0) throw new SocketError(socket_strerror($err));
        }
        if ($number <= 0) continue;
        if (FALSE === ($client = @socket_accept($this->socket))) {
          $err = socket_last_error();
          $this->logger->debug(socket_strerror($err));
          if ($err === SOCKET_EAGAIN or $err === SOCKET_ECONNABORTED) continue;
          throw new SocketError(socket_strerror($err));
        }
        if (is_resource($client)) {
          $this->process_client($client);
        }
        ftruncate($this->alive, 0);
      } catch (ErrorException $e) {
        $this->logger->error($e->getTraceAsString());
      }
    }

    $this->logger->info('worker exits');
    exit;
  }

  function process_client($client) { }

  function spawn_missing_workers() {
    $nrs = array_map(function($w) { return $w->nr; }, $this->workers);
    foreach (range(0, $this->opt['workers'] - 1) as $nr) {
      if (in_array($nr, $nrs)) continue;
      $worker = new stdClass;
      $worker->nr = $nr;
      $worker->tmp = _tmpio();
      $pid = pcntl_fork();
      if ($pid == -1) {
        $this->logger->info('failed to fork worker'); 
      }
      else if ($pid == 0) $this->worker_loop($worker);
      else $this->workers[$pid] = $worker;
    }
  }

  function downsize_workers() {
    while (list($pid, $worker) = each($this->workers)) {
      if (count($this->workers) <= $this->opt['workers']) break;
      posix_kill($pid, SIGQUIT);
      fclose($worker->tmp);
      unset($this->workers[$pid]);
    }
  }

  function kill_each_worker($sig) {
    foreach ($this->workers as $pid => $worker) {
      posix_kill($pid, $sig);
      $this->logger->debug("sent $sig to worker $worker->nr");
      fclose($worker->tmp);
      unset($this->workers[$pid]);
    }
  }

  function reap_all_workers() {
    while (TRUE) {
      if (($pid = pcntl_wait($status, WNOHANG)) < 1) break;
      if (isset($this->workers[$pid])) {
        $worker = $this->workers[$pid];
        fclose($worker->tmp);
        unset($this->workers[$pid]);
        $this->logger->debug("reaped worker $worker->nr ($pid)");
      }
    }
  }

  function maintain_worker_count() {
    if ($balance = count($this->workers) - $this->opt['workers']) {
      return $balance > 0 ?
        $this->downsize_workers() :
        $this->spawn_missing_workers();
    }
  }

  function murder_lazy_workers() {
    foreach ($this->workers as $wpid => $worker) {
      $stat = fstat($worker->tmp);
      if (($diff = (time() - $stat['ctime'])) <= $this->opt['timeout']) continue;
      $this->logger->info(
        "worker=$worker->nr PID:$wpid timeout "
        . "({$diff}s > {$this->opt['timeout']}s), killing"
      );
      posix_kill($wpid, SIGKILL);
    }
  }

  function call_app($env) {
    return call_user_func($this->app, $env);
  }


  function build($inner_app) {
    $apps = $this->apps;
    while ($class = array_pop($apps)) {
      if (is_array($class)) {
        $run = $class[0];
        $arg = $class[1];
        $outer_app = new $run($inner_app, $arg);
      }
      else $outer_app = new $class($inner_app);
      if (is_callable($outer_app)) {
        $inner_app = $outer_app;
      }
      else $this->logger->warning("ignoring $class instance: it was not callable");
    }
    $this->app = $inner_app;
  }

  function start($app) {
    $this->build($app);
    $this->master_loop();
  }

}

function _init_signals($server) {
  static $signals = array(
    SIGQUIT, // Exit gracefully
    SIGWINCH,
    SIGINT, // Keyboard exit
    SIGTERM, // Exit
    SIGUSR1,
    SIGUSR2,
    SIGHUP,
    SIGTTIN, // Increase workers
    SIGTTOU, // Decrease workers
  );

  foreach ($signals as $sig) {
    pcntl_signal($sig, array($server, 'handle_signal'));
  }
}

function _init_pipe(&$server) {
  foreach ($server->pipe as $end) @socket_close($end);
  if (FALSE === socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $server->pipe)) {
    throw new SocketError(socket_strerror(socket_last_error()));
  }
}

function _tmpio() {
  if (FALSE === (
    ($filename = tempnam(sys_get_temp_dir(), 'pip')) and
    ($handle = fopen($filename, 'w+'))
  )) throw new ErrorException('failed creating tmpio');
  if (FALSE === unlink($filename)) $this->logger->error('could not unlink tmpio');
  return $handle;
}
