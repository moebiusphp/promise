<?php
namespace Moebius;

use Closure;
use Moebius\Promise\ProtoPromise;

class Promise extends ProtoPromise {

    public function __construct(Closure $resolveFunction) {
        $resolveFunction($this->fulfill(...), $this->reject(...));
    }

    /**
     * Check carefully that the provided value is a promise-like
     * object.
     */
    public static final function isPromise($promise): bool {
        if (!\is_object($promise)) {
            return false;
        }

        if (!\method_exists($promise, 'then')) {
            return false;
        }

        if (
            $promise instanceof PromiseInterface ||
            (class_exists(\GuzzleHttp\Promise\PromiseInterface::class, false) && $promise instanceof \GuzzleHttp\Promise\PromiseInterface) ||
            (class_exists(\React\Promise\PromiseInterface::class, false) && $promise instanceof \React\Promise\PromiseInterface) ||
            (class_exists(\Http\Promise\Promise::class, false) && $promise instanceof \Http\Promise\Promise)
        ) {
            return true;
        }

        $rm = new \ReflectionMethod($promise, 'then');
        if ($rm->isStatic()) {
            return false;
        }
        foreach ($rm->getParameters() as $index => $rp) {
            if ($rp->hasType()) {
                $rt = $rp->getType();
                if ($rt instanceof \ReflectionNamedType) {
                    if (
                        $rt->getName() !== 'mixed' &&
                        $rt->getName() !== 'callable' &&
                        $rt->getName() !== \Closure::class
                    ) {
                        return false;
                    }
                } else {
                }
            }
            if ($rp->isVariadic()) {
                return true;
            }
            if ($index === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cast a promise-like object to a Moebius\Promise.
     */
    public static final function cast(object $promise): PromiseInterface {
        if ($promise instanceof PromiseInterface) {
            return $promise;
        }
        if (!self::isPromise($promise)) {
            throw new \TypeError("Expected a promise-like object");
        }
        $result = ProtoPromise::getInstance();
        $promise->then($result->fulfill(...), $result->reject(...));
        return $result;
    }

    /**
     * When all promises have been fulfilled, or if one promise rejects.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     */
    public static final function all(iterable $promises): Promise {
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
    public static final function allSettled(iterable $promises): Promise {
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
    public static final function any(iterable $promises): Promise {
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
    public static final function race(iterable $promises): Promise {
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


}
