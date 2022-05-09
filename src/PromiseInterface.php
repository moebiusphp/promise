<?php
namespace Moebius\Promise;

use React\Promise\PromiseInterface as ReactPromiseInterface;
use Amp\Promise as AmpPromiseInterface;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use Http\Promise\Promise as PhpHttpPromiseInterface;

interface PromiseInterface extends ReactPromiseInterface, AmpPromiseInterface, GuzzlePromiseInterface {

    public function status(): string;
    public function value(): mixed;
    public function reason(): mixed;
    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $onProgress=null);
    public function fulfill(mixed $value=null): void;
    public function reject(mixed $reason=null): void;

}
