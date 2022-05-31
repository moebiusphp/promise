<?php

use Moebius\Promise;

try {
    $p = new Promise(function($yes, $no) {
        throw new \Exception("A");
    });
} catch (\Throwable $e) {
    echo $e->getMessage();
}

// Here we're overwriting the $p object
$p = new Promise(function($yes, $no) {
    $no(new \Exception("B"));
});
$p->then(function($value) {
    echo "FAIL";
}, function($e) {
    echo $e->getMessage();
});

echo "C\n";
