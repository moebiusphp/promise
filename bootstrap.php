<?php
/**
 * Which libraries are in use?
 */
use Composer\InstalledVersions;
use Moebius\Promise\EmptyInterface;

/**
 * Moebius Promises require the GuzzleHttp\Promise\PromiseInterface.
 *
 * It is impossible to implement both GuzzleHttp\Promise\PromiseInterface and
 * Http\Promise\Promise in a single class. Fortunately Guzzle promises is a
 * can extend php-http promises.
 *
 * If php-http/promise is installed, we'll use that as a parent-interface
 * for our promises. If not, and the original is available, use that.
 * Finally, if neither is available - we'll use an empty placeholder
 * interface.
 */
if (InstalledVersions::isInstalled('php-http/promise')) {
    class_alias(
        \Http\Promise\Promise::class,
        \GuzzleHttp\Promise\PromiseInterface::class
    );
} elseif (!InstalledVersions::isInstalled('guzzlehttp/promises')) {
    class_alias(
        \Moebius\Promise\GuzzleHttpPromiseInterface::class,
        \GuzzleHttp\Promise\PromiseInterface::class
    );
}

/**
 * Moebius Promises require the React\Promise\PromiseInterface
 *
 * If React is not installed, we will use an empty placeholder
 * interface.
 */
if (!InstalledVersions::isInstalled('react/promise')) {
    class_alias(
        \Moebius\Promise\ReactPromiseInterface::class,
        \React\Promise\PromiseInterface::class
    );
}

/**
 * Moebius Promises require the Amp\Promise interface.
 *
 * If Amp is not installed, we will use an empty placeholder
 * interface.
 */
if (!InstalledVersions::isInstalled('amphp/amp')) {
    class_alias(
        \Moebius\Promise\AmpPromiseInterface::class,
        \Amp\Promise::class
    );
}
