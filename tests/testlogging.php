<?php

include 'logging.php';

use pip\logging;

logging\basicConfig(logging\ERROR);

logging\debug('hello');
logging\info('hello');
logging\warning('hello');
logging\error('hello');
logging\critical('hello');
