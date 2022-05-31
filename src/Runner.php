<?php
namespace Moebius\Promise;

/**
 * Class will run callbacks when it becomes garbage collected,
 * to ensure deferred execution of callbacks in a system context,
 * as per the Promises/A+ specification.
 *
 * PHP will immediately garbage collect this because it contains
 * no cyclic references.
 *
 * @internal
 */
final class Runner {

    private array $callbacks;
    private mixed $result;

    public function __construct(array $callbacks, mixed $result) {
        $this->callbacks = $callbacks;
        $this->result = $result;
    }

    public function __destruct() {
        foreach ($this->callbacks as $k => $callback) {
            unset($this->callbacks[$k]);
            try {
                $callback($this->result);
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
}
