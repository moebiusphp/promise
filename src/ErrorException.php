<?php
namespace Moebius\Promise;

use Throwable;

class ErrorException extends Exception {

    public readonly mixed $original;

    public function __construct(string $message, mixed $value, Throwable $previous) {
        $this->original = $value;
        $message .= ". Original value is in the \$original property";
        parent::__construct($message, 0, $previous);
    }

}
