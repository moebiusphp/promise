<?php
/**
 * This bootstrap.php file checks if chass interfaces for various popular
 * Promise implementations are installed via Composer. Since Moebius
 * Promise implements these interfaces, we must ensure that the interface
 * exists. This should be harmless, since if an application does not depend
 * on for example React\Promise\PromiseInterface, then declaring an empty
 * interface as a placeholder will cause no other side effects.
 *
 * Http\Promise\Promise and GuzzleHttp\Promise\PromiseInterface are
 * impossible to implement in a single class implementation because the
 * interfaces declare the same constants. Since Http\Promise\Promise is
 * a subset of GuzzleHttp\Promise\PromiseInterface - we are making
 * GuzzleHttp\Promise\PromiseInterface an alias of Http\Promise\Promise.
 */
use Composer\InstalledVersions;
use Moebius\Promise\EmptyInterface;

/**
 * Moebius Promises require the GuzzleHttp\Promise\PromiseInterface.
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
        \React\Promise\ExtendedPromiseInterface::class
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
