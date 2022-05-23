moebius/promise
===============

A pure and well tested promise implementation designed for interoperability.

Casting a promise
-----------------

Promises from other implementations can be cast to Moebius\Promise.

```php
<?php
$promise = Moebius\Promise::cast($otherPromise);
```

Identifying a promise-like object
---------------------------------

Since there is no canonical PHP promise interface, it is useful to have a
simple way to check if an object appears to be a valid promise.

```php
<?php
if (Moebius\Promise::isPromise($otherPromise)) {
    // promise has a valid `then()` method
}
```

Creating a promise
------------------

Promises can generally be created in two ways; with a resolver function
or without a resolver function - in which case the promise must be resolved
by calling the `fulfill()` or the `reject()` method.

*With a resolver function**

```php
<?php
$promise = new Promise(function(callable $fulfill, callable $reject) {
    $fulfill("Hello World");
});
$promise->then(function($value) {
    echo $value."\n";
});
```

*Without a resolver function*

```php
<?php
$promise = new Promise();
$promise->then(function($value) {
    echo $value."\n";
});
$promise->resolve("Hello World");
```


Interoperability Notes
----------------------

The interface is focused on the resolution of promises; it does not care about
how a promise is created - the interface is for functions that accept a promise.

Most promise implementations have an extensive API, but a promise only needs
to expose a `then()` function to be usable by 90% of libraries.

It is also very helpful to have a way to determine if a promise is already
resolved or rejected - which generally is implemented using a method which
returns a string "pending", "resolved", "rejected" or "fulfilled".

This library implements methods `isPending()`, `isResolved()` and `isFulfilled()`
because these methods can be implemented regardless of how the underlying
promise implementation records that state..

Many promise implementations have methods like `otherwise()`, `finally()` and
similar - which may be convenient, but it reduces interoperability because
the same features can be implemented in several ways and with different names.

A promise implementation which is widely compatible with various libraries.

Modelled after the Promises/A+ specification in JavaScript.

If you create a `Moebius\Promise` instance, it is directly usable as a drop-in
replacement for:

 * `guzzlehttp/promises` by implementing `GuzzleHttp\Promise\PromiseInterface`
 * `react/promise` by implementing `React\Promise\PromiseInterface`
 * 'php-http/promise' by implementing `Http\Promise\Promise`
 * `amphp/amp` by implementing `Amp\Promise`

The `Moebius\Promise` objects have two primary uses: As a standard `Promise`
object, and as a `Deferred` object using React terminology.


Basic Promise Usage
-------------------

This is the most common way to use a promise:

```php
use Moebius\Promise;

function some_future_result() {
    return new Promise(function($fulfill, $reject) {
        /**
         * Either fulfill the promise directly here, by calling
         * the provided $fulfill(VALUE) and $reject(REASON) callbacks
         * immediately, or make sure that one of these are called at
         * a later time.
         */
    });
}
```


"Deferred" usage
----------------

In React and some other libraries, an additional type of promise
is called a "deferred" promise. Moebius combines these two uses:

```php
use Moebius\Promise;

function some_future_result() {
    $result = new Promise();

    /**
     * Make sure that the promise is resolved now, by calling
     * `$result->resolve(VALUE)` or `$result->reject(REASON)`
     * here, or make sure that one of them will be called in
     * the future.
     */

    return $result;
}
```

Supporting other promises yourself
----------------------------------

To support other promise implementations yourself, the convention
has been to use reflection to inspect the `then()` method.

Moebius Promise provides two ways to support other promises
and ensure a consistent usage:

### Casting

```php
use Moebius\Promise;

function accepting_a_promise(object $thenable) {
    /**
     * @throws InvalidArgumentException if the object is not a promise
     */
    $promise = Moebius\Promise::cast($thenable);
}
```

Implementation details
----------------------

In order to maintain compatability both Guzzle and PhpHttp promises, we
have made some "trickery". It turns out that PhpHttp-promises are modelled
after Guzzle promises, and are in fact compatible. Guzzle promises are
a superset of PhpHttp promises, so if PhpHttp is installed - it will be
`class_alias()`'ed to replace the `GuzzleHttp\Promise\PromiseInterface`.

These constants now live in the `Moebius\Promise\SuperPromiseInterface` class,
so that they are available in both child classes while not being ambiguous
according to PHP.

Promise resolution
------------------

Guzzle is quite particular when it comes to promise resolution, in that it
will defer running the resolve function until you actually request the
value. This is not ideal for an event loop implementation - because, for
example if your task takes 1 second to run - you would want to start running
that task as soon as possible. This is not noticeable when only using it
with Guzzle, but when combined with other async tasks it becomes annoying.

We don't expect this to be a problem.

Promise::wait()
----------------

Guzzle and PhpHttp implements a `wait()` function, which is intended to
start running the asynchronous jobs - almost like using the promise tree
as an event-loop. 


Basic usage
-----------

Normally you will pass a resolve function into the promise when it is
constructed. This variant is the most common way to use promises.

```php
use Moebius\Promise;

function some_function() {
    return new Promise(function($resolve, $reject) {
        // This function is immediately run when constructing the promise

        $resolve("Some value");         // or $reject(new Exception());
    });
}
```


Deferred usage
--------------

In some cases, you are unable to resolve the promise from inside the promise.
This way you can hold on to a reference of the promise and resolve (or reject) it after
you return it.

```php
use Moebius\Promise;

$promise = new Promise();

// send the promise off to some function
some_function($promise);

// resolve it at any later time
$promise->resolve("Some value");    // or $promise->reject(new Exception())
```

Note: While is possible to call the resolve/reject methods multiple times, only
the *first* call has an effect.


API documentation
-----------------

Handling multiple promises efficiently:

* `Promise::all(iterable $promises): Promise`. If *all* promises are resolved,
  the returned promise will resolve with an array of all the promises. If any
  of the promises are rejected, the returned promise is also rejected.

* `Promise::allSettled(iterable $promises): Promise`. Once all the promise have
  a result, the returned promise is resolved with an array of the promises.

* `Promise::any(iterable $promises): Promise`. If any of the promises are resolved,
  the returned promise is resolved with the first value. If *all* promises are 
  rejected, the returned promise is rejected.

* `Promise::race(iterable $promises): Promise`. The first promise which is either
  resolved or rejected will be the result of the returned promise.


Interoperability with other promise implementations:

* `Promise::cast($promise): Promise`. Any object with a promise-like 'then' function
  is converted into a Moebius\Promise instance.


Subscribing to the result of a promise:

* `$promise->then(callable $onFulfilled=null, callable $onRejected=null): Promise`. The
  passed functions will be invoked immediately if the promise has already been
  settled (fulfilled or rejected). The returned promise is a new promise which
  will be resolved or rejected with the value from the callbacks.


Resolving a promise externally:

* `$promise->resolve($value)`.

* `$promise->reject($reason)`.
