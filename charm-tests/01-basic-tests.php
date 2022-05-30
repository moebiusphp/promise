<?php

use Moebius\Promise;

$p = new Promise(function($yes, $no) {
    $yes("A");
});

$p->then(function($value) {
    echo $value;
    echo "B";
    return "C";
})->then(function($value) {
    echo $value."\n";
});;
