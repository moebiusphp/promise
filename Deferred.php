<?php
namespace Moebius;

use Closure;
use Psr\Log\LoggerInterface;
use Moebius\Promise\UncaughtPromiseException;

class Deferred extends Promise\ProtoPromise {

    public function fulfill(mixed $value) {
        parent::fulfill($value);
    }

    public function reject(mixed $reason) {
        parent::reject($reason);
    }

}
