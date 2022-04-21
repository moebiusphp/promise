<?php
namespace Moebius\Promise;

use Moebius\Promise;

trait PromiseTrait {
    private mixed $result = null;
    private string $status = Promise::PENDING;
    private ?array $resolvers = [];
    private ?array $rejectors = [];
    private ?array $childPromises = [];
    private bool $fromThenable = false;

    public function __construct(callable $resolver=null) {
        $this->Promise($resolver);
    }

    protected function Promise(callable $resolver=null) {
        if ($resolver !== null) {
            $resolver($this->resolve(...), $this->reject(...));
        }
    }

    /**
     * Return 'pending', 'fulfilled', 'rejected'
     */
    public function status(): string {
        return $this->status;
    }

    /**
     * Return value if promise is fulfilled
     */
    public function value(): mixed {
        if ($this->status !== Promise::FULFILLED) {
            throw new Promise\Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    /**
     * Return reason if promise is rejected
     */
    public function reason(): mixed {
        if ($this->status !== Promise::REJECTED) {
            throw new Promise\Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    public function then(callable $onFulfilled=null, callable $onRejected=null): PromiseInterface {
        $nextPromise = new Promise();
        if ($onFulfilled !== null && $this->status !== Promise::REJECTED) {
            $this->resolvers[] = function($result) use ($onFulfilled, $nextPromise) {
                try {
                    $nextResult = $onFulfilled($result);
                    $nextPromise->resolve($nextResult);
                } catch (\Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        if ($onRejected !== null && $this->status !== Promise::FULFILLED) {
            $this->rejectors[] = function($reason) use ($onRejected, $nextPromise) {
                try {
                    $nextRejection = $onRejected($reason);
                    $nextPromise->reject($nextRejection);
                } catch (\Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        $this->settle();
        return $nextPromise;
    }

    public function otherwise(callable $onRejected): PromiseInterface {
        return $this->then(null, $onRejected);
    }

    /**
     * Get the current status of the promise (for compatability with other
     * promise implementations).
     */
    public function getState(): string {
        return $this->status;
    }

    /**
     * Resolve the promise with a value
     */
    public function resolve(mixed $result=null): void {
        if ($this->fromThenable) {
            throw new Promise\Exception("Promise was cast from Thenable and can't be externally resolved");
        }
        if (Promise::isThenable($result)) {
            $result->then($this->resolve(...), $this->reject(...));
            return;
        }
        if ($this->status !== Promise::PENDING) {
            return;
        }
        $this->status = Promise::FULFILLED;
        $this->result = $result;
        $this->settle();
    }

    /**
     * Reject the promise with a reason
     */
    public function reject(mixed $reason=null): void {
        if ($this->fromThenable) {
            throw new Promise\Exception("Promise was cast from Thenable and can't be externally rejected");
        }
        if (Promise::isThenable($reason)) {
            $reason->then($this->reject(...), $this->reject(...));
            return;
        }
        if ($this->status !== Promise::PENDING) {
            return;
        }
        $this->status = Promise::REJECTED;
        $this->result = $reason;
        $this->settle();
    }

    private function settle(): void {
        if ($this->status === Promise::FULFILLED) {
            $resolvers = $this->resolvers;
            $this->resolvers = [];
            $this->rejectors = null;
            foreach ($resolvers as $resolver) {
                $resolver($this->result);
            }
        } elseif ($this->status === Promise::REJECTED) {
            $rejectors = $this->rejectors;
            $this->resolvers = null;
            $this->rejectors = [];
            foreach ($rejectors as $rejector) {
                $rejector($this->result);
            }
        }
    }
}
