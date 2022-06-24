<?php
namespace Moebius\Promise;

class Rejected extends ProtoPromise {

    public function __construct(mixed $reason) {
        $this->reject($reason);
    }

}
