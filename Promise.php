<?php
namespace Moebius;

use Psr\Log\LoggerInterface;
use Moebius\Promise\UncaughtPromiseException;

class Promise implements PromiseInterface {

    /**
     * Object pool size.
     */
    public static int $poolSize = 100;

    /**
     * A configurable logger which will get notified whenever promises
     * are rejected without a rejection-handler. This is a common source
     * of bugs because such exceptions would be silently discarded.
     */
    public static ?LoggerInterface $logger = null;


    private static array $pool = [];
    private static int $poolIndex = 0;

    private const PENDING = 0;
    private const FULFILLED = 1;
    private const REJECTED = 2;

    // True if the promise is being resolved by another promise
    private bool $pendingPromise = false;

    private int $status = self::PENDING;
    private mixed $result = null;
    private array $onFulfilled = [];
    private array $onRejected = [];
    private bool $errorDelivered = false;

    /**
     * We will only add secondary promises to our object pool for now.
     */
    private bool $secondary = false;

    public function __construct(callable $resolveFunction=null) {
        if ($resolveFunction !== null) {
            // Exceptions thrown in the resolve-function are not caught
            // because the error belongs to the place where the promise
            // is created. If the exception needs to go to the receiver
            // of the promise - call $reject(new \Exception()) for example.
            $resolveFunction($this->fulfill(...), $this->reject(...));
        }
    }

    public function __destruct() {
        $status = $this->status;
        $errorDelivered = $this->errorDelivered;
        $result = $this->result;

        // Add this promise to our promise pool?
        if ($this->secondary && self::$poolIndex < self::$poolSize) {
            $this->status = self::PENDING;
            $this->result = null;
            $this->onFulfilled = [];
            $this->onRejected = [];
            self::$pool[self::$poolIndex++] = $this;
        }

        if ($status === self::REJECTED && !$errorDelivered) {
            if (self::$logger === null) {
                self::$logger = \Charm\FallbackLogger::get();
            }
            $message = "Uncaught (in promise) ";
            $context = [];
            if ($result instanceof \Throwable) {
                $message .= "{className}#{code}: {message} in {file}:{line}";
                $context['className'] = \get_class($result);
                $context['code'] = $result->getCode();
                $context['message'] = $result->getMessage();
                $context['file'] = $result->getFile();
                $context['line'] = $result->getLine();
                $context['exception'] = $result;
            } else {
                $message .= "{debugType}";
                $context['debugType'] = \get_debug_type($result);
                $context['value'] = $result;
            }
            self::$logger->error($message, $context);
        }
    }

    public function isPending(): bool {
        return $this->status === self::PENDING;
    }

    public function isFulfilled(): bool {
        return $this->status === self::FULFILLED;
    }

    public function isRejected(): bool {
        return $this->status === self::REJECTED;
    }

    public function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface {
        // We need a secondary promise to return - getting from the instance pool
        $promise = self::getInstance();

        $onFulfillHandler = null;
        $onRejectHandler = null;

        if ($onFulfill && $this->status !== self::REJECTED) {
            // no reason to create an onFulfillHandler if the promise is rejected
            $onFulfillHandler = static function($value) use ($promise, $onFulfill, &$onFulfillHandler, &$onRejectHandler) {
                try {
                    $result = $onFulfill($value);
                    $promise->fulfill($result);
                } catch (\Throwable $e) {
                    $promise->reject($e);
                }
            };
        }

        if ($onReject && $this->status !== self::FULFILLED) {
            // no reason to create an onRejectHandler if the promise is fulfilled
            $onRejectHandler = static function($reason) use ($promise, $onReject, &$onFulfillHandler, &$onRejectHandler) {
                // Promise was rejected in a simple way
                try {
                    $result = $onReject($reason);
                    $promise->fulfill($result);
                } catch (\Throwable $e) {
                    $promise->reject($e);
                }
            };
        }

        $this->onFulfilled[] = $onFulfillHandler ?? $promise->fulfill(...);
        if ($onRejectHandler) {
            $this->errorDelivered = true;
            $this->onRejected[] = $onRejectHandler;
        } else {
            $this->onRejected[] = $promise->reject(...);
        }

        if ($this->status !== self::PENDING) {
            $this->settle();
        }

        return $promise;
    }

    public function fulfill(mixed $value): void {
        if ($this->status !== self::PENDING) {
            return;
        }

        if (is_object($value) && self::isPromise($value)) {
            $value->then($this->fulfill(...), $this->reject(...));
            return;
        }

        $this->onRejected = [];
        $this->status = self::FULFILLED;
        $this->result = $value;
        $this->settle();
    }

    public function reject(mixed $value): void {
        if ($this->status !== self::PENDING || $this->pendingPromise) {
            return;
        }

        $this->onFulfilled = [];
        $this->status = self::REJECTED;
        $this->result = $value;
        $this->settle();
    }

    private function settle(): void {
        if ($this->status === self::FULFILLED) {
            $callbacks = $this->onFulfilled;
            $this->onFulfilled = [];
        } elseif ($this->status === self::REJECTED) {
            $callbacks = $this->onRejected;
            $this->onRejected = [];
        } else {
            throw new \LogicException("Promise is not ready to settle");
        }
        foreach ($callbacks as $callback) {
            $callback($this->result);
        }
    }

    public static function isThenable(object $promise): bool {
        return self::isPromise($promise);
    }

    public static function isPromise($promise): bool {
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

    public static function cast(object $promise): PromiseInterface {
        if ($promise instanceof PromiseInterface) {
            return $promise;
        }
        if (!self::isPromise($promise)) {
            throw new \TypeError("Expected a promise-like object");
        }
        $result = new Promise();
        $promise->then($result->fulfill(...), $result->reject(...));
        return $result;
    }

    /**
     * Get a secondary promise instance. These promises are very often not
     * used for anything, so we're using an object pool to avoid needless
     * garbage collection.
     */
    private static function getInstance(callable $resolveFunction=null) {
        if (self::$poolIndex > 0) {
            return self::$pool[--self::$poolIndex];
        }
        $promise = new self($resolveFunction);
        $promise->secondary = true;
        // secondary instances does not have special error handling
        $promise->errorDelivered = true;
        return $promise;
    }

}
