moebius/promise
===============

A simple and flexible Promise implementation modelled after Promises/A+ in
JavaScript.

Compatbile with both React and Amp.


Basic usage
-----------

Normally you will pass a resolve function into the promise when it is
constructed. This variant is the most common way to use promises.

```
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

```
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


Checking the status of a promise:

* `$promise->status()` gives 'pending', 'fulfilled', 'rejected' or 'cancelled'.
  The 'cancelled' status shouldn't be used unless in very special cases.

* `$promise->getState()` is an alias for `$promise->status()`.

* `$promise->value()` gives the result of the promise, as long as the status is
  'fulfilled'.

* `$promise->reason()` gives the rejection value (normally an Exception object).


Subscribing to the result of a promise:

* `$promise->then(callable $onFulfilled=null, callable $onRejected=null): Promise`. The
  passed functions will be invoked immediately if the promise has already been
  settled (fulfilled or rejected). The returned promise is a new promise which
  will be resolved or rejected with the value from the callbacks.

* `$promise->otherwise(callable $onRejected=null): Promise`. Works identically to
  `$promise->then(null, $onRejected)`.


Resolving a promise externally:

* `$promise->resolve($value)`.

* `$promise->reject($reason)`.
