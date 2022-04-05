<?php
namespace Moebius;

use function method_exists;
use ReflectionFunction, ReflectionNamedType, ReflectionUnionType;

class Promise {

    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

    /**
     * Cast a Thenable class into a Promise
     */
    public static function cast(object $thenable): self {
        if ($thenable instanceof Promise) {
            return $thenable;
        }

        static::assertThenable($thenable);

        $promise = new static(function($resolve, $reject) use ($thenable) {
            $thenable->then($resolve, $reject);
        });
        $promise->fromThenable = true;
    }

    /**
     * When all promises have fulfilled, or if one promise rejects
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     */
    public static function all(iterable $promises): Promise {
        $promise = new static();
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
        $promise = new static();
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
        $promise = new static();
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
        $promise = new static();
        foreach ($promises as $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->resolve($result);
            }, function($reason) use ($promise) {
                $promise->reject($reason);
            });
        }
        return $promise;
    }

    private mixed $result = null;
    private string $status = self::PENDING;
    private ?array $resolvers = [];
    private ?array $rejectors = [];
    private ?array $childPromises = [];
    private bool $fromThenable = false;

    public function __construct(callable $resolver=null) {
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
        if ($this->status !== self::FULFILLED) {
            throw new Promise\Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    /**
     * Return reason if promise is rejected
     */
    public function reason(): mixed {
        if ($this->status !== self::REJECTED) {
            throw new Promise\Exception("Promise is in the '".$this->status."' state");
        }
        return $this->result;
    }

    public function then(callable $onFulfilled=null, callable $onRejected=null): Promise {
        $nextPromise = new static();
        if ($onFulfilled !== null && $this->status !== self::REJECTED) {
            $this->resolvers[] = function($result) use ($onFulfilled, $nextPromise) {
                try {
                    $nextResult = $onFulfilled($result);
                    $nextPromise->resolve($nextResult);
                } catch (\Throwable $e) {
                    $nextPromise->reject($e);
                }
            };
        }
        if ($onRejected !== null && $this->status !== self::FULFILLED) {
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

    private function settle(): void {
        if ($this->status === self::FULFILLED) {
            $resolvers = $this->resolvers;
            $this->resolvers = [];
            $this->rejectors = null;
            foreach ($resolvers as $resolver) {
                $resolver($this->result);
            }
        } elseif ($this->status === self::REJECTED) {
            $rejectors = $this->rejectors;
            $this->resolvers = null;
            $this->rejectors = [];
            foreach ($rejectors as $rejector) {
                $rejector($this->result);
            }
        }
    }

    public function otherwise(callable $onRejected): Promise {
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
        if (self::isThenable($result)) {
            $result->then($this->resolve(...), $this->reject(...));
            return;
        }
        if ($this->status !== self::PENDING) {
            return;
        }
        $this->status = self::FULFILLED;
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
        if (self::isThenable($reason)) {
            $reason->then($this->reject(...), $this->reject(...));
            return;
        }
        if ($this->status !== self::PENDING) {
            return;
        }
        $this->status = self::REJECTED;
        $this->result = $reason;
        $this->settle();
    }

    /**
     * Check that the entire array contains only objects with a then-method
     */
    private static function assertOnlyPromises(iterable $promises): void {
        foreach ($promises as $promise) {
            static::assertThenable($promise);
        }
    }

    private static function isThenable(mixed $thenable): bool {
        if (!is_object($thenable)) {
            return false;
        }
        if ($thenable instanceof Promise) {
            return true;
        }
        if (!method_exists($thenable, 'then')) {
            return false;
        }
        $rf = new ReflectionFunction($thenable->then(...));
        if ($rf->getNumberOfParameters() < 2) {
            return false;
        }
        $rp = $rf->getParameters();
        foreach ([0, 1] as $p) {
            if (!$rp[$p]->hasType()) {
                continue;
            }
            $rt = $rp[$p]->getType();

            if ($rt instanceof ReflectionNamedType && $rt->getName() === 'callable') {
                continue;
            } elseif ($rt instanceof ReflectionUnionType) {
                foreach ($rt->getTypes() as $rst) {
                    if ($rt->getName() === 'callable') {
                        continue 2;
                    }
                }
            }
            return false;
        }
        return true;
    }

    private static function assertThenable(object $thenable): void {
        if ($thenable instanceof Promise) {
            return;
        }
        if (!method_exists($thenable, 'then')) {
            throw new Promise\Exception("Class '".get_class($thenable)."' is not 'Thenable' class.");
        }
        $rf = new ReflectionFunction($thenable->then(...));
        if ($rf->getNumberOfParameters() < 2) {
            throw new Promise\Exception("Class '".get_class($thenable)."' is not 'Thenable' class.");
        }
        $rp = $rf->getParameters();
        foreach ([0, 1] as $p) {
            if (!$rp[$p]->hasType()) {
                continue;
            }
            $rt = $rp[$p]->getType();

            if ($rt instanceof ReflectionNamedType && $rt->getName() === 'callable') {
                continue;
            } elseif ($rt instanceof ReflectionUnionType) {
                foreach ($rt->getTypes() as $rst) {
                    if ($rt->getName() === 'callable') {
                        continue 2;
                    }
                }
            }
            throw new Promise\Exception("Class '".get_class($thenable)."' is not 'Thenable' class (argument ".(1+$p)." has an unsupported type).");
        }
    }

}
