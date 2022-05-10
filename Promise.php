<?php
namespace Moebius;

use function method_exists;

use ReflectionMethod, ReflectionNamedType, ReflectionUnionType;
use Moebius\Promise\{
    PromiseInterface,
    PromiseTrait
};
use React\Promise\ExtendedPromiseInterface as ReactExtendedPromiseInterface;
use React\Promise\PromiseInterface as ReactPromiseInterface;
use Amp\Promise as AmpPromiseInterface;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use Http\Promise\Promise as PhpHttpPromiseInterface;


class Promise implements PromiseInterface, ReactExtendedPromiseInterface, AmpPromiseInterface, GuzzlePromiseInterface {
    use PromiseTrait {
        then as private _then;
    }

    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

    /**
     * The cancel-function is invoked when a promise is cancelled. A cancelled promise behaves
     * exactly as a rejected promise which throws the CancelledException.
     */
    private ?Closure $cancelFunction;

    /**
     * When implementing this trait, if your class has a costructor you must call `$this->Promise()`
     * yourself.
     *
     * @param callable $resolver    Function that when invoked resolves the promise.
     * @param callable $cancel      Function that will be invoked when a promise is cancelled via `Promise::cancel()`.
     */
    public function __construct(callable $resolver=null, callable $cancel=null) {
        $this->cancelFunction = $cancel;
        $this->Promise($resolver);
    }

    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $void=null) {
        return $this->_then($onFulfilled, $onRejected);
    }

    /**
     * @see React\Promise\ExtendedPromiseInterface::done()
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null) {
        $this->then($onFulfilled, $onRejected);
        return;
    }

    /**
     * Registers a callback to be invoked when the promise is resolved (fulfilled or rejected).
     *
     * NOTE! The first argument of the handler function is used if the promise was rejected,
     * and the second argument is used if the promise is fulfilled. This is according to Amp\Promise.
     *
     * @see Amp\Promise::onResolve()
     */
    public function onResolve(callable $handler) {
        $this->then(function($value) use ($handler) {
            $handler(null, $value);
        }, function($value) use ($handler) {
            $handler($value, null);
        });
    }

    /**
     * Registers a callback to be invoked when the promise is rejected.
     *
     * @see React\Promise\ExtendedPromiseInterface::otherwise()
     */
    public function otherwise(callable $onRejected): PromiseInterface {
        return $this->then(null, $onRejected);
    }

    /**
     * Get the current status of the promise (for compatability with other
     * promise implementations).
     *
     * @see GuzzleHttp\Promise\PromiseInterface::getState()
     * @see Http\Promise\Promise::getState()
     */
    public function getState(): string {
        if ($this->isPending()) {
            return self::PENDING;
        } elseif ($this->isFulfilled()) {
            return self::FULFILLED;
        } else {
            return self::REJECTED;
        }
    }

    /**
     * Alias of {@see self::fulfill} implemented for compatability with 
     * `GuzzleHttp\Promise\PromiseInterface`.
     *
     * NOTE! "Resolved" can also mean that a promise is no longer pending
     * resolution. Both rejected and fulfilled promises can be considered
     * to be resolved. In GuzzleHttp\Promise\PromiseInterface the word
     * 'resolved' means 'fulfilled' - not 'rejected'.
     */
    public function resolve($value) {
        $this->fulfill($value);
    }

    /**
     * Cancel this promise. The effect of cancelling a promise is that it
     * is rejected with the return value `null`. If a cancel-function was
     * provided when the promise was constructed, that cancel-function will
     * be invoked.
     *
     * @see GuzzleHttp\Promise\PromiseInterface::cancel()
     */
    public function cancel() {
        if ($this->isPending() && is_callable($this->cancelFunction)) {
            try {
                ($this->cancelFunction)();
            } catch (\Throwable $e) {
                if ($this->isPending()) {
                    $this->reject($e);
                } else {
                    throw $e;
                }
            }
            $this->cancelFunction = null;
        }
        if ($this->isPending()) {
            // if the state is still pending we must ensure it is cancelled
            $this->reject(null);
        }
    }

    /**
     * Run scheduled callbacks, event loops and coroutines until this promise is resolved.
     *
     * If $unwrap == true the function will return the fulfillment value from the promise,
     * or throw the rejection value.
     *
     * If $unwrap == false the function will return a promise which will resolve when
     * this promise is resolved.
     *
     * @param bool $unwrap
     * @throws \Throwable
     * @see GuzzleHttp\Promise\PromiseInterface::wait()
     * @see Http\Promise\Promise::wait()
     */
    public function wait($unwrap = true) {
        if ($unwrap) {
            if (class_exists(\Moebius\Coroutine::class)) {
                return \Moebius\Coroutine::await($this);
            }
            throw new \LogicException("Can't unwrap a promise without an event loop currently. Not implemented. You can try to install moebius/coroutine to solve this.");
        } else {
            return $this->then(null, null);
        }
    }

    /**
     * @see React\Promise\ExtendedPromiseInterface::progress()
     */
    public function progress(callable $onProgress) {
        // Functionality is deprecated in react and ignored here
    }

    /**
     * @see React\Promise\ExtendedPromiseInterface::always()
     */
    public function always(callable $onFulfilledOrRejected) {
        $resolver = static function($value) use ($onFulfilledOrRejected) {
            $onFulfilledOrRejected($value);
            return $value;
        };
        return $this->then($resolver, $resolver);
    }

    /**
     * Cast a Thenable class into a Promise
     */
    public static function cast(object $thenable): self {
        if ($thenable instanceof Promise) {
            return $thenable;
        }

        static::assertThenable($thenable);

        $promise = new self(function($resolve, $reject) use ($thenable) {
            $thenable->then($resolve, $reject);
        });

        return $promise;
    }

    /**
     * Check if an object is promise-like, or "thenable". This is for compatability
     * with other promise implementations.
     *
     * @param object $thenable The object to check
     * @return bool Returns true if the object appears to be promise like.
     */
    public static function isThenable(mixed $thenable): bool {
        if (!is_object($thenable)) {
            return false;
        }
        if (
            $thenable instanceof PromiseInterface ||
            $thenable instanceof GuzzlePromiseInterface ||
            $thenable instanceof ReactPromiseInterface ||
            $thenable instanceof PhpHttpPromiseInterface
        ) {
            return true;
        }

        if (!method_exists($thenable, 'then')) {
            return false;
        }

        $rf = new ReflectionMethod($thenable, 'then');
        if ($rf->isStatic()) {
            return false;
        }
        if ($rf->getNumberOfParameters() < 2) {
            return false;
        }
        $rp = $rf->getParameters();
        foreach ([0, 1] as $p) {
            if (!$rp[$p]->hasType()) {
                continue;
            }
            $rt = $rp[$p]->getType();

            if (
                $rt instanceof ReflectionNamedType &&
                (
                    $rt->getName() === 'callable' ||
                    $rt->getName() === 'mixed' ||
                    $rt->getName() === \Closure::class
                )
            ) {
                continue;
            } elseif ($rt instanceof ReflectionUnionType) {
                foreach ($rt->getTypes() as $rst) {
                    if (
                        $rt->getName() === 'callable' ||
                        $rt->getName() === 'mixed' ||
                        $rt->getName() === \Closure::class
                    ) {
                        continue 2;
                    }
                }
            }
            return false;
        }
        return true;
    }

    /**
     * When all promises have been fulfilled, or if one promise rejects.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     */
    public static function all(iterable $promises): Promise {
        $promise = new self();
        $results = [];
        $offset = 0;
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $theirPromise = static::cast($theirPromise);
            $theirPromise->then(function(mixed $result) use (&$results, $offset, &$counter, $promise) {
                $results[$offset] = $result;
                if (--$counter === 0) {
                    $promise->resolve($results);
                }
            }, function(mixed $reason) use ($promise) {
                $promise->reject($reason);
            });
            $offset++;
        }
        return $promise;
    }

    /**
     * When all promises have settled, provides an array of settled promises.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/allSettled
     */
    public static function allSettled(iterable $promises): Promise {
        $promise = new self();
        $results = [];
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $results[] = $theirPromise = static::cast($theirPromise);
            $theirPromise->then(function(mixed $result) use (&$counter, $promise, &$results) {
                if (--$counter === 0) {
                    $promise->resolve($results);
                }
            });
        }
        return $promise;
    }

    /**
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/any
     */
    public static function any(iterable $promises): Promise {
        $promise = new self();
        $errors = [];
        $counter = count($promises);
        foreach ($promises as $offset => $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->resolve($result);
            }, function($reason) use ($promise, &$counter, $offset, &$errors) {
                $errors[$offset] = $reason;
                if (--$counter === 0) {
                    $promise->reject($errors);
                }
            });
        }
        return $promise;
    }

    /**
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/race
     */
    public static function race(iterable $promises): Promise {
        $promise = new self();
        foreach ($promises as $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->resolve($result);
            }, function($reason) use ($promise) {
                $promise->reject($reason);
            });
        }
        return $promise;
    }

    private static function assertThenable(object $thenable): void {
        if (!self::isThenable($thenable)) {
            throw new \InvalidArgumentException("Object of class '".get_class($thenable)."' is not a valid promise-like object.");
        }
    }

}
