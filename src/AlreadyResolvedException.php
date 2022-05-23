<?php
namespace Moebius\Promise;

class AlreadyResolvedException extends \LogicException {

    public function __construct() {
        parent::__construct("The Promise has already been resolved");
    }

}
