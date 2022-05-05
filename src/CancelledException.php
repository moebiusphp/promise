<?php
namespace Moebius\Promise;

class CancelledException extends \Exception {
    public function __construct(string $message='Promise has been cancelled', int $code=0, \Throwable $previous=null) {
        parent::__construct($message, $code, $previous);
    }
}
