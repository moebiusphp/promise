<?php
require(__DIR__.'/../vendor/autoload.php');

use Moebius\{
    Promise,
    Deferred
};

echo "Testing resolutions\n";

$resolutions = [
    [ new Promise(function($resolve, $reject) { $resolve(true); }), true ],
    [ new Promise(function($resolve, $reject) { $resolve(false); }), false ],
    [ new Promise(function($resolve, $reject) { $resolve(null); }), null ],
];
foreach ($resolutions as list($promise, $expected)) {
    $promise->then(function($result) use ($expected) {
        if ($result === $expected) {
            echo "Promise result OK: ".json_encode($expected)."\n";
        } else {
            echo "Incorrect result: ".json_encode($result)." but expected ".json_encode($expected)."\n";
        }
    }, function($reason) {
        echo "Failed with reason ".json_encode($reason)."\n";
    });
}
echo "\n";

echo "Testing rejections\n";

$resolutions = [
    [ new Promise(function($resolve, $reject) { $reject(true); }), true ],
    [ new Promise(function($resolve, $reject) { $reject(false); }), false ],
    [ new Promise(function($resolve, $reject) { $reject(null); }), null ],
];
foreach ($resolutions as list($promise, $expected)) {
    $promise->then(function($result) {
        echo "Failed with result ".json_encode($result)."\n";
    }, function($result) use ($expected) {
        if ($result === $expected) {
            echo "Promise rejection OK: ".json_encode($expected)."\n";
        } else {
            echo "Incorrect rejection: ".json_encode($result)." but expected ".json_encode($expected)."\n";
        }
    });
}
echo "\n";

echo "Testing chained promises:\n";

$p = new Deferred();
$p->then(function($reason) {
    echo " 1. Resolved with reason ".json_encode($reason)."\n";
    return "First one done.";
})->then(function($reason) {
    echo " 2. Resolved with reason ".json_encode($reason)."\n";
    return "Second one done.";
})->then(function($reason) {
    echo " 3. Resolved with reason ".json_encode($reason)."\n";
    return "Third one done.";
})->then(function($reason) {
    echo " 4. Final one with reason ".json_encode($reason)."\n";
    return new Promise(function($resolve) {
        $resolve("This came from a returned promise");
    });
})->then(function($reason) {
    echo "Finally we have this result from a promise returned in the last one: ".json_encode($reason)."\n";
});


$p->fulfill("Starting to resolve them");
