<?php
namespace Moebius\Promise;

use Throwable, Closure;
use Moebius\Promise;

trait PromiseTrait {
    private mixed $result = null;
    private string $status = Promise::PENDING;
    private ?array $onFulfilledListeners = [];
    private ?array $onRejectionListeners = [];
    private ?array $childPromises = [];

    /**
     * True if it should not be possible to use {@see self::fulfill()} or {@see self::reject()}
     * to fulfill the promise. This is used when wrapping another promise.
     */
    private bool $unresolvable = false;

    /**
     * A promise owns the error if the promise is not an indirect promise returned via
     * {@see self::then()}.
     */
    protected bool $isErrorOwner = true;

    /**
     * A throwable which holds the stack trace at the location where the promise was constructed.
     * This is helpful when debugging promises.
     */
    protected Throwable $creationTrace;

    /**
     * Destructor helps with the difficult to debug case where a promise is
     * garbage collected without any notification because a developer forgot
     * to listen to the on rejected state.
     */
    public function __destruct() {
        if ($this->status === Promise::REJECTED && $this->isErrorOwner) {
            if ($this->result instanceof \Throwable) {
                throw $this->result;
            } else {
                throw new ErrorException("Unhandled promise rejection", $this->result, $this->creationTrace);
            }
        }
    }

    /**
     * Actual constructor - provided for classes that use this trait with a custom
     * constructor.
     */
    protected function Promise(callable $resolver=null) {
        // Record the stack trace for when this promise was creationTrace
        $this->creationTrace = new ExceptionConstructor($resolver);

        // immediately invoke the resolver function
        if ($resolver !== null) {
            try {
                $resolver($this->fulfill(...), $this->reject(...));
            } catch (\Throwable $e) {
                $this->reject($e);
            }
        }
    }

    /**
     * Is the promise fulfilled?
     */
    public function isFulfilled(): bool {
        return $this->status === Promise::FULFILLED;
    }

    /**
     * Is the promise rejected?
     */
    public function isRejected(): bool {
        return $this->status === Promise::REJECTED;
    }

    /**
     * Is the promise still waiting to be resolved?
     */
    public function isPending(): bool {
        return $this->status === Promise::PENDING;
    }

    /**
     * Return value if promise is fulfilled
     */
    public function value(): mixed {
        if ($this->status !== Promise::FULFILLED) {
            throw new \LogicException("Promise is not fulfilled", 0, $this->creationTrace);
        }
        return $this->result;
    }

    /**
     * Return reason if promise is rejected
     */
    public function reason(): mixed {
        if ($this->status !== Promise::REJECTED) {
            throw new PromiseException("Promise is not rejected", 0, $this->creationTrace);
        }
        return $this->result;
    }

    /**
     * Add listeners to onFulfilled and/or onRejected events.
     *
     * @see React\Promise\PromiseInterface::then()
     * @see GuzzleHttp\Promise\PromiseInterface::then()
     * @see Http\Promise\Promise::then()
     */
    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $void=null): PromiseInterface {
        $nextPromise = new DerivedPromise($this->creationTrace, $this->isErrorOwner);
        if ($onFulfilled !== null && $this->status !== Promise::REJECTED) {
            $this->onFulfilledListeners[] = function($result) use ($onFulfilled, $nextPromise) {
                try {
                    $nextResult = $onFulfilled($result);
                    $nextPromise->fulfill($nextResult);
                } catch (Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        if ($onRejected !== null && $this->status !== Promise::FULFILLED) {
            $this->onRejectionListeners[] = function($reason) use ($onRejected, $nextPromise) {
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
     * Fulfill the promise.
     *
     * @see GuzzleHttp\Promise\PromiseInterface::fulfill()
     * @see Http\Promise\Promise::fulfill()
     */
    public function fulfill(mixed $result=null): void {
        if (!$this->isPending()) {
            throw new \LogicException("Can't fulfill a promise twice");
        }
        if ($this->unresolvable) {
            throw new PromiseException("Promise was cast from Thenable and can't be externally fulfilled", 0, $this->creationTrace);
        }
        if (Promise::isThenable($result)) {
            $result->then($this->fulfill(...), $this->reject(...));
            return;
        }
        $this->status = Promise::FULFILLED;
        $this->result = $result;
        $this->settle();
    }

    /**
     * Reject the promise with a reason. The $reason should generally be an exception
     * but any value is valid.
     *
     * @see GuzzleHttp\Promise\PromiseInterface::reject()
     * @see Http\Promise\Promise::reject()
     */
    public function reject(mixed $reason=null): void {
        if (!$this->isPending()) {
            throw new \LogicException("Can't fulfill a promise twice");
        }
        if ($this->unresolvable) {
            throw new PromiseException("Promise was cast from Thenable and can't be externally rejected", 0, $this->creationTrace);
        }
        if (Promise::isThenable($reason)) {
            $reason->then($this->reject(...), $this->reject(...));
            return;
        }
        $this->status = Promise::REJECTED;
        $this->result = $reason;
        $this->settle();
    }

    /**
     * Function handles notifying of all listeners when the promise is resolved.
     */
    private function settle(): void {
        if ($this->status === Promise::FULFILLED) {
            $onFulfilledListeners = $this->onFulfilledListeners;

            // avoid possible memory leaks:
            $this->onFulfilledListeners = [];
            $this->onRejectionListeners = null;

            foreach ($onFulfilledListeners as $fulfiller) {
                $fulfiller($this->result);
            }
        } elseif ($this->status === Promise::REJECTED) {
            $onRejectionListeners = $this->onRejectionListeners;

            // avoid possible memory leaks:
            $this->onFulfilledListeners = null;
            $this->onRejectionListeners = [];

            if (!empty($onRejectionListeners)) {
                // We don't need to show this error anymore
                $this->isErrorOwner = false;
            }
            foreach ($onRejectionListeners as $rejector) {
                $rejector($this->result);
            }
        }
    }
}
