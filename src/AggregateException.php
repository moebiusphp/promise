<?php
namespace Moebius\Promise;

class AggregateException extends \Exception {
    public readonly $errors;

    public function __construct(iterable $errors) {
        $this->errors = \iterator_to_array($errors);
        $errorTexts = [];
        foreach ($errors as $error) {
            if (!($error instanceof \Throwable)) {
                throw new \LogicException("AggregateException can only be built from other exceptions");
            }
            $errorTexts[] = $error->getMessage()." (code=".$error->getCode().")";
        }
        parent::__construct("Multiple errors: ".implode(", ", $errorTexts));
    }
}
