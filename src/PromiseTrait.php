<?php
namespace Moebius\Promise;

use Throwable, Closure;
use Moebius\Promise;

trait PromiseTrait {
    private mixed $result = null;
    private string $status = Promise::PENDING;
    private ?array $fulfillers = [];
    private ?array $rejectors = [];
    private ?array $childPromises = [];
    private bool $fromThenable = false;
//    private ?array $creator = null;
    private bool $isErrorOwner = true;
    private Throwable $created;
    private ?Closure $cancelFunction;

    /**
     * @param callable $resolver    Function that when invoked resolves the promise.
     * @param callable $cancel      Function that will be invoked when a promise is cancelled via `Promise::cancel()`.
     */
    public function __construct(callable $resolver=null, callable $cancel=null) {
        $this->Promise($resolver, $cancel);
    }

    public function __destruct() {
        if ($this->status === Promise::REJECTED && $this->isErrorOwner) {
            if ($this->result instanceof \Throwable) {
                throw $this->result;
            } else {
                throw new ErrorException("Unhandled promise rejection", $this->result, $this->created);
            }
        }
    }

    protected function Promise(callable $resolver=null, callable $cancel=null) {
        // the generator stores the backtrace
        $this->created = new ExceptionConstructor($resolver);

        $this->cancelFunction = $cancel;

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
            throw new PromiseException("Promise is not fulfilled", 0, $this->created);
        }
        return $this->result;
    }

    /**
     * Return reason if promise is rejected
     */
    public function reason(): mixed {
        if ($this->status !== Promise::REJECTED) {
            throw new PromiseException("Promise is not rejected", 0, $this->created);
        }
        return $this->result;
    }

    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $_onProgress=null): PromiseInterface {
        $nextPromise = new Promise();
        $nextPromise->created = $this->created;
        $nextPromise->isErrorOwner = &$this->isErrorOwner;
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
            throw new PromiseException("Promise was cast from Thenable and can't be externally fulfilled", 0, $this->created);
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
     * Alias of {@see self::fulfill} implemented for compatability with
     * `GuzzleHttp\Promise\PromiseInterface`.
     */
    public function resolve($value) {
        $this->fulfill($value);
    }

    /**
     * Implemented for compatability with `GuzzleHttp\Promise\PromiseInterface`.
     */
    public function cancel() {
        if ($this->state === Promise::PENDING && is_callable($this->cancelFunction)) {
            try {
                ($this->cancelFunction)();
            } catch (\Throwable $e) {
                if ($this->state === Promise::PENDING) {
                    $this->reject($e);
                } else {
                    throw $e;
                }
            }
            $this->cancelFunction = null;
        }
        if ($this->state === Promise::PENDING) {
            // if the state is still pending we must ensure it is cancelled
            $this->reject(new CancelledException());
        }
    }

    public function wait($unwrap = true) {
        if ($unwrap) {
            if (class_exists(\Moebius\Coroutine::class)) {
                return \Moebius\Coroutine::await($unwrap);
            }
            throw new \Exception("Can't unwrap a promise without an event loop currently. Not implemented. You can try to install moebius/coroutine to solve this.");
        } else {
            return $this->then(null, null);
        }
    }

    /**
     * Reject the promise with a reason
     */
    public function reject(mixed $reason=null): void {
        if ($this->fromThenable) {
            throw new PromiseException("Promise was cast from Thenable and can't be externally rejected", 0, $this->created);
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

            if (!empty($rejectors)) {
                // We don't need to show this error anymore
                $this->isErrorOwner = false;
            }
            foreach ($rejectors as $rejector) {
                $rejector($this->result);
            }
        }
    }
}
