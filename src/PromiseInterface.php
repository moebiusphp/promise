<?php
namespace Moebius\Promise;

/**
 * A compatible promise implementation for PHP, compatible with the most
 * popular promise implementations in PHP.
 */
interface PromiseInterface {

    /**
     * Is the promise fulfilled?
     */
    public function isFulfilled(): bool;

    /**
     * Is the promise rejected?
     */
    public function isRejected(): bool;

    /**
     * Is the promise unresolved?
     */
    public function isPending(): bool;

    /**
     * Get the value of a resolved promise.
     *
     * @return mixed
     * @throws \LogicException if the promise is not in the "fulfilled" state
     */
    public function value(): mixed;

    /**
     * Get the reason of a rejected promise.
     *
     * @return mixed
     * @throws \LogicException if the promise is not in the "rejected" state
     */
    public function reason(): mixed;

    /**
     * Add fulfill and/or reject listeners to the promise. Third argument
     * is for compatability with React Promises and are ignored.
     */
    public function then(callable $onFulfilled=null, callable $onRejected=null, callable $void=null);
}
