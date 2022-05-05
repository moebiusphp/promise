<?php
namespace Moebius\Promise;

/**
 * This exception is used to store the stack trace for when
 * the promise was initially created.
 */
class ExceptionConstructor extends \Exception {
    public function __construct(callable $resolver=null) {
        if ($resolver !== null) {
            $rf = new \ReflectionFunction($resolver);
            $message = $rf->getName()." in ".$rf->getFileName().":".$rf->getStartLine();
        } else {
            $message = "Deferred Promise";
        }
        parent::__construct($message);
    }
}
