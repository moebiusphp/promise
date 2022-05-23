<?php
namespace Moebius\Promise;

/**
 * Exception thrown if a promise is rejected without
 * any listeners and without a configured logger for
 * this particular case.
 */
class UnhandledException extends \LogicException {

}
