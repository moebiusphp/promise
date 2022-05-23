<?php
namespace Moebius;

/**
 * A pure promise interface, designed for interoperability. This interface can
 * be implemented by most known promise implementations, including React, Amp
 * and GuzzleHttp.
 *
 * The rationale for the design of this interface is that it provides all the
 * neccesary functionality to retrieve a future value for any consumer of the
 * promise. Other functionality such as promise resolution is particular to the
 * source that created the promise.
 *
 * The `then()` method allows a third argument because some promise implementations
 * implement alternative semantics such as progress-updates or on-cancellation.
 *
 */
interface PromiseInterface {

    /**
     * Schedule a callback to run when the promise is fulfilled
     * or rejected.
     *
     * @param callable $onFulfill   Callback which will be invoked if the promise is fulfilled.
     * @param callable $onReject    Callback which will be invoked if the promise is rejected.
     * @param callable $void        Ignored; for compataiblity with other promise implementations.
     * @return PromiseInterface     Returns a new promise which is resolved with the return value of $onFulfill/$onReject
     */
    public function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface;

    /**
     * Is the promise still pending resolution?
     */
    public function isPending(): bool;

    /**
     * Is the promise fulfilled?
     */
    public function isFulfilled(): bool;

    /**
     * Is the promise rejected?
     */
    public function isRejected(): bool;

}
