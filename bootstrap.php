<?php
/**
 * Only load AmpPromiseInterface if Amp is not already available in this project.
 */
if (!class_exists(Amp\Promise::class)) {
    require(__DIR__.'/lib/AmpPromiseInterface.php');
}

/**
 * Only load GuzzlePromiseInterface if GuzzleHttp is not already available in the project.
 */
if (!class_exists(GuzzleHttp\Promise\PromiseInterface::class)) {
    require(__DIR__.'/lib/GuzzlePromiseInterface.php');
}
