<?php
namespace Moebius\Promise;

use Psr\Log\LoggerInterface;
use Charm\FallbackLogger;

final class Logger {

    private static ?LoggerInterface $logger = null;

    public static function set(LoggerInterface $logger): void {
        self::$logger = $logger;
    }

    public static function get(): LoggerInterface {
        if (self::$logger === null) {
            self::$logger = FallbackLogger::get();
        }
        return self::$logger;
    }

}
