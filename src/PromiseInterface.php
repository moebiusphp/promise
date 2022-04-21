<?php
namespace Moebius\Promise;

interface PromiseInterface {

    public function status(): string;
    public function value(): mixed;
    public function reason(): mixed;
    public function then(callable $onFulfilled=null, callable $onRejected=null): self;
    public function resolve(mixed $value=null): void;
    public function reject(mixed $reason=null): void;

}
