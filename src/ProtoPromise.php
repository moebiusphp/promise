<?php
namespace Moebius\Promise;

use Moebius\Promise;
use Moebius\PromiseInterface;
use Moebius\Promise\{
    Logger,
    Runner,
    UncaughtPromiseException,
    AlreadyResolvedException
};

/**
 * This promise implementation is designed to be extended.
 *
 * @internal
 */
class ProtoPromise implements PromiseInterface {

    /**
     * Pool of reusable ProtoPromise-promises. These objects
     * are returned and often not used - so we take care to
     * use them again and again to avoid unneccesary garbage
     * collection.
     */
    private static int $poolSize = 100;
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
    private array $queue = [];

    public function __destruct() {
        while (!empty($this->queue)) {
            $queue = $this->queue;
            $this->queue = [];
            foreach ($queue as $callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {

                    $message = "Uncaught (in promise callback) {className} code={code}: {message} in {file}:{line}";
                    $context = [
                        'className' => \get_class($e),
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'exception' => $e
                    ];
                    Logger::get()->error($message, $context);
                }
            }
        }
        $status = $this->status;
        $errorDelivered = $this->errorDelivered;
        $result = $this->result;

        // Add this promise to our promise pool?
        if (\get_class($this) === self::class && self::$poolIndex < self::$poolSize) {
            $this->status = self::PENDING;
            $this->result = null;
            $this->onFulfilled = [];
            $this->onRejected = [];

            self::$pool[self::$poolIndex++] = $this;
        }

        if ($status === self::REJECTED && !$errorDelivered) {
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
            Logger::get()->error($message, $context);
        }
    }

    public final function isPending(): bool {
        return $this->status === self::PENDING;
    }

    public final function isFulfilled(): bool {
        return $this->status === self::FULFILLED;
    }

    public final function isRejected(): bool {
        return $this->status === self::REJECTED;
    }

    public final function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface {
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

    protected function fulfill(mixed $value) {
        if ($this->status !== self::PENDING) {
            throw new AlreadyResolvedException();
        }

        if ($value === $this) {
            $this->result = new \TypeError("Promise resolved with itself");
            $this->status = self::REJECTED;
            $this->onFulfilled = [];
            return $this->settle();
        }

        if (is_object($value) && Promise::isPromise($value)) {
            try {
                $value->then($this->fulfill(...), $this->reject(...));
            } catch (\Throwable $e) {
                $this->reject($e);
            }
            return null;
        }

        $this->onRejected = [];
        $this->status = self::FULFILLED;
        $this->result = $value;
        return $this->settle();
    }

    protected function reject(mixed $value) {
        if ($this->status !== self::PENDING || $this->pendingPromise) {
            throw new AlreadyResolvedException();
        }

        if ($value === $this) {
            $this->result = new \TypeError("Promise rejected with itself");
            $this->status = self::REJECTED;
            $this->onFulfilled = [];
            $this->settle();
            return null;
        }

        $this->onFulfilled = [];
        $this->status = self::REJECTED;
        $this->result = $value;
        return $this->settle();
    }

    private function settle() {
        if ($this->status === self::FULFILLED) {
            $callbacks = $this->onFulfilled;
        } elseif ($this->status === self::REJECTED) {
            $callbacks = $this->onRejected;
        } else {
            throw new \LogicException("Promise is not ready to settle");
        }
        $this->onFulfilled = [];
        $this->onRejected = [];
        return new Runner($callbacks, $this->result);
    }

    /**
     * Get a secondary promise instance. These promises are very often not
     * used for anything, so we're using an object pool to avoid needless
     * garbage collection.
     */
    protected static function getInstance(callable $resolveFunction=null) {
        if (self::$poolIndex > 0) {
            return self::$pool[--self::$poolIndex];
        }
        $promise = new self($resolveFunction);
        // secondary instances does not have special error handling
        $promise->errorDelivered = true;
        return $promise;
    }
}
