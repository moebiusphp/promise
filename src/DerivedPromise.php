<?php
namespace Moebius\Promise;

use Throwable, Closure;
use Moebius\Promise;

class DerivedPromise implements PromiseInterface {
    use PromiseTrait;

    public function __construct(ExceptionConstructor $creationTrace, bool &$isErrorOwner) {
        $this->creationTrace = $creationTrace;
        $this->isErrorOwner = &$isErrorOwner;
    }

}
