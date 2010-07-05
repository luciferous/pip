<?php

namespace pip\webapp\template;

function render($file, $subs = array()) {
  ob_start();
  extract($subs);
  include($file);
  return ob_get_clean();
}
