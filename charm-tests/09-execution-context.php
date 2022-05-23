<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\Promise;

$p = new Promise(function($yes, $no) {
    $yes("A");
});

$p->then(function($value) {
    var_dump(debug_backtrace());
});
