<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\Promise;

try {
    $p = new Promise(function($yes, $no) {
        throw new \Exception("A");
    });
} catch (\Throwable $e) {
    echo $e->getMessage();
}

$p = new Promise(function($yes, $no) {
    $no(new \Exception("B"));
});
$p->then(function($value) {
    echo "FAIL";
}, function($e) {
    echo $e->getMessage();
});

echo "C\n";
