<?php
use Moebius\Promise;

$p = new Promise(function($yes, $no) {
    echo "A";
    $no(new \Exception("B"));
});
$p->then(function() {
    echo "resolved\n";
})->then(null, function($e) {
    echo $e->getMessage();;
});

echo "C\n";
