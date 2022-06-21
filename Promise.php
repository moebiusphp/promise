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
     * The Promise.all() method takes an iterable of promises as an input, and returns a single
     * Promise that resolves to an array of the results of the input promises. This returned promise
     * will resolve when all of the input's promises have resolved, or if the input iterable contains
     * no promises. It rejects immediately upon any of the input promises rejecting or non-promises
     * throwing an error, and will reject with this first rejection message / error.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     */
    public static final function all(iterable $promises): PromiseInterface {
        $promise = ProtoPromise::getInstance();
        $results = [];
        $offset = 0;
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $results[$offset] = null;
            static::cast($theirPromise)->then(function(mixed $result) use (&$results, $offset, &$counter, $promise) {
                $results[$offset] = $result;
                if (--$counter === 0) {
                    $promise->fulfill($results);
                }
            }, function(mixed $reason) use ($promise) {
                $promise->reject($reason);
            });
            $offset++;
        }
        if ($results === []) {
            $promise->fulfill([]);
        }
        return $promise;
    }

    /**
     * The Promise::allSettled() method returns a promise that resolves after all of the given
     * promises have either fulfilled or rejected, with an array of objects that each describes
     * the outcome of each promise.
     *
     * It is typically used when you have multiple asynchronous tasks that are not dependent on
     * one another to complete successfully, or you'd always like to know the result of each
     * promise.
     *
     * In comparison, the Promise returned by Promise::all() may be more appropriate if the
     * tasks are dependent on each other / if you'd like to immediately reject upon any of them
     * rejecting.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/allSettled
     */
    public static final function allSettled(iterable $promises): PromiseInterface {
        $promise = ProtoPromise::getInstance();
        $results = [];
        $counter = count($promises);
        foreach ($promises as $theirPromise) {
            $results[] = $theirPromise = static::cast($theirPromise);
            $theirPromise->then(function(mixed $result) use (&$counter, $promise, &$results) {
                if (--$counter === 0) {
                    $promise->fulfill($results);
                }
            });
        }
        return $promise;
    }

    /**
     * Promise.any() takes an iterable of Promise objects. It returns a single promise that resolves
     * as soon as any of the promises in the iterable fulfills, with the value of the fulfilled
     * promise. If no promises in the iterable fulfill (if all of the given promises are rejected),
     * then the returned promise is rejected with an AggregateException, a subclass of \Exception that
     * groups together individual errors.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/any
     */
    public static final function any(iterable $promises): PromiseInterface {
        $promise = ProtoPromise::getInstance();
        $errors = [];
        $counter = count($promises);
        foreach ($promises as $offset => $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->fulfill($result);
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
     * The Promise::race() method returns a promise that fulfills or rejects as soon as one of the promises
     * in an iterable fulfills or rejects, with the value or reason from that promise.
     *
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/race
     */
    public static final function race(iterable $promises): PromiseInterface {
        $promise = ProtoPromise::getInstance();
        foreach ($promises as $theirPromise) {
            static::cast($theirPromise)->then(function($result) use ($promise) {
                $promise->fulfill($result);
            }, function($reason) use ($promise) {
                $promise->reject($reason);
            });
        }
        return $promise;
    }


}
