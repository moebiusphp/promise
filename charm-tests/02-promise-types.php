<?php
require(__DIR__."/../vendor/autoload.php");

use Moebius\Promise;

function cast_test($o) {
    try {
        $p = Promise::cast($o);
        return true;
    } catch (\Throwable $e) {
        //echo get_class($e).": ".$e->getMessage()." in ".$e->getFile().":".$e->getLine()."\n";
        return false;
    }
}

assert(!cast_test(new class {}), "new class {}");

assert(!cast_test(new class {
    public function then() {}
}), "new class {
    public function then() {}
}");

assert(cast_test(new class {
    public function then($a, $b) {}
}), 'new class {
    public function then($a, $b) {}
}');

assert(!cast_test(new class {
    public function then(int $a, $b) {}
}), 'new class {
    public function then(int $a, $b) {}
}');

assert(!cast_test(new class {
    public function then($a, int $b) {}
}), 'new class {
    public function then($a, int $b) {}
}');

assert(!cast_test(new class {
    public static function then($a, $b) {}
}), 'new class {
    public static function then($a, $b) {}
}');
