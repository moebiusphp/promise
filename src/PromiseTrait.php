<?php
namespace Moebius\Promise;

use Throwable;
use Moebius\Promise;

trait PromiseTrait {
    private mixed $result = null;
    private string $status = Promise::PENDING;
    private ?array $fulfillers = [];
    private ?array $rejectors = [];
    private ?array $childPromises = [];
    private bool $fromThenable = false;

    public function __construct(callable $resolver=null) {
        $this->Promise($resolver);
    }

    protected function Promise(callable $resolver=null) {
        if ($resolver !== null) {
            $resolver($this->fulfill(...), $this->reject(...));
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

    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $_onProgress=null): PromiseInterface {
        $nextPromise = new Promise();
        if ($onFulfilled !== null && $this->status !== Promise::REJECTED) {
            $this->fulfillers[] = function($result) use ($onFulfilled, $nextPromise) {
                try {
                    $nextResult = $onFulfilled($result);
                    $nextPromise->fulfill($nextResult);
                } catch (Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        if ($onRejected !== null && $this->status !== Promise::FULFILLED) {
            $this->rejectors[] = function($reason) use ($onRejected, $nextPromise) {
                try {
                    $nextRejection = $onRejected($reason);
                    $nextPromise->reject($nextRejection);
                } catch (Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        $this->settle();
        return $nextPromise;
    }

    /**
     * From Amp\Promise
     * ----------------
     *
     * Registers a callback to be invoked when the promise is resolved.
     *
     * If this method is called multiple times, additional handlers will be registered instead of replacing any already
     * existing handlers.
     *
     * If the promise is already resolved, the callback MUST be executed immediately.
     *
     * Exceptions MUST NOT be thrown from this method. Any exceptions thrown from invoked callbacks MUST be
     * forwarded to the event-loop error handler.
     *
     * Note: You shouldn't implement this interface yourself. Instead, provide a method that returns a promise for the
     * operation you're implementing. Objects other than pure placeholders implementing it are a very bad idea.
     *
     * @param callable $onResolved The first argument shall be `null` on success, while the second shall be `null` on
     *     failure.
     *
     * @psalm-param callable(Throwable|null, mixed): (Promise|\React\Promise\PromiseInterface|\Generator<mixed,
     *     Promise|\React\Promise\PromiseInterface|array<array-key, Promise|\React\Promise\PromiseInterface>, mixed,
     *     mixed>|null) | callable(Throwable|null, mixed): void $onResolved
     *
     * @return void
     */
    public function onResolve(callable $handler) {
        $this->then(function($value) use ($handler) {
            $handler(null, $value);
        }, function($value) use ($handler) {
            $handler($value, null);
        });
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
     * Fulfill the promise with a value
     */
    public function fulfill(mixed $result=null): void {
        if ($this->fromThenable) {
            throw new Promise\Exception("Promise was cast from Thenable and can't be externally fulfilled");
        }
        if (Promise::isThenable($result)) {
            $result->then($this->fulfill(...), $this->reject(...));
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
            $fulfillers = $this->fulfillers;

            // avoid possible memory leaks:
            $this->fulfillers = [];
            $this->rejectors = null;

            foreach ($fulfillers as $fulfiller) {
                $fulfiller($this->result);
            }
        } elseif ($this->status === Promise::REJECTED) {
            $rejectors = $this->rejectors;

            // avoid possible memory leaks:
            $this->fulfillers = null;
            $this->rejectors = [];

            if (empty($rejectors)) {
                // This error will be hidden, so throw it or raise error
                if ($this->result instanceof Throwable) {
                    throw $this->result;
                } else {
                    trigger_error((string) $this->result);
                }
            } else {
                foreach ($rejectors as $rejector) {
                    $rejector($this->result);
                }
            }
        }
    }
}
