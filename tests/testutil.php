<?php

namespace test;

include 'util.php';

use pip\util;

$opt = new util\Opt();
$opt->handle('h', 'help', array($opt, 'help'));
$opt->handle('w', 'workers');
$options = $opt->parse($argv);
$options['workers'] = $opt->get('w', 'workers');
$options['iface'] = $opt->next();
$options['port'] = $opt->next();
