<?php
namespace Moebius\Promise;

/**
 * Empty interface only used if `amphp/amp` is not
 * installed.
 */
interface GuzzleHttpPromiseInterface {

    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

}
