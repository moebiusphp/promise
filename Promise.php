<?php
namespace Moebius;

use function method_exists;

use ReflectionFunction, ReflectionNamedType, ReflectionUnionType;
use Moebius\Promise\{
    PromiseInterface,
    PromiseTrait
};

class Promise implements PromiseInterface {
    use PromiseTrait;

    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

    /**
     * Cast a Thenable class into a Promise
     */
    public static function cast(object $thenable): self {
        if ($thenable instanceof PromiseInterface) {
            return $thenable;
        }

        static::assertThenable($thenable);

        $promise = new self(function($resolve, $reject) use ($thenable) {
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
        if ($thenable instanceof PromiseInterface) {
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
        if ($thenable instanceof PromiseInterface) {
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
