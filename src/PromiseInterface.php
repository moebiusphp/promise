<?php
namespace Moebius\Promise;

use React\Promise\PromiseInterface as ReactPromiseInterface;
use Amp\Promise as AmpPromiseInterface;

interface PromiseInterface extends ReactPromiseInterface, AmpPromiseInterface {

    public function status(): string;
    public function value(): mixed;
    public function reason(): mixed;
    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $onProgress=null);
    public function fulfill(mixed $value=null): void;
    public function reject(mixed $reason=null): void;

}
