<?php
namespace Moebius\Promise;

class Fulfilled extends ProtoPromise {

    public function __construct(mixed $value) {
        $this->fulfill($value);
    }

}
