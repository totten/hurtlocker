<?php

namespace Hurtlocker;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$hl = new Hurtlocker();
$exit = $hl->main(file_get_contents('php://stdin'));
exit($exit);
