<?php
use Moebius\Promise;

$promise = new Promise(function($fulfill, $reject) {
    $fulfill(new Promise(function($fulfill, $reject) {
        $fulfill("Great success");
    }));

});

$promise->then(function($result) {
    assert(is_string($result), "Expected a string");
    echo $result."\n";
    return new Promise(function($fulfill, $reject) {
        $fulfill("More success");
    });
}, function($reason) {
    assert(false, "This shouldn't happen");
})
->then(function($result) {
    assert(is_string($result), "Expected a string");
    echo $result."\n";
}, function($reason) {
    assert(false, "This shouldn't happen");
});
