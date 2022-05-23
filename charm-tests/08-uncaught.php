<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\Promise;

echo "Promise 1\n";
$promise1 = new Promise(function($yes, $no) {
    $no(new \Exception("This should get logged"));
});
unset($promise1);

echo "Promise 2\n";
$promise2 = new Promise(function($yes, $no) {
    $no(new \Exception("This should get logged too"));
});
$promise2->then(function($e) {
    echo "fulfilled with '".get_class($e)."'\n";
});
unset($promise2);

echo "Promise 3\n";
$promise3 = new Promise(function($yes, $no) {
    $no(new \Exception("This should not get logged"));
});
$promise3->then(null, function($e) {
    echo "This is expected\n";
});
unset($promise3);

