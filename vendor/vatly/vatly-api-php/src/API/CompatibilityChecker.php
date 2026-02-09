<?php

declare(strict_types=1);

namespace Vatly\API;

use Vatly\API\Exceptions\IncompatiblePlatformException;

class CompatibilityChecker
{
    /**
     * @var string
     */
    public const MIN_PHP_VERSION = "7.4";

    /**
     * @return void
     * @throws IncompatiblePlatformException
     */
    public function checkCompatibility()
    {
        if (! $this->satisfiesPhpVersion()) {
            throw new IncompatiblePlatformException(
                "The client requires PHP version >= " . self::MIN_PHP_VERSION . ", you have " . PHP_VERSION . ".",
                IncompatiblePlatformException::INCOMPATIBLE_PHP_VERSION
            );
        }

        if (! $this->satisfiesJsonExtension()) {
            throw new IncompatiblePlatformException(
                "PHP extension json is not enabled. Please make sure to enable 'json' in your PHP configuration.",
                IncompatiblePlatformException::INCOMPATIBLE_JSON_EXTENSION
            );
        }
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    public function satisfiesPhpVersion(): bool
    {
        return (bool)version_compare(PHP_VERSION, self::MIN_PHP_VERSION, ">=");
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    public function satisfiesJsonExtension(): bool
    {
        // Check by extension_loaded
        if (function_exists('extension_loaded') && extension_loaded('json')) {
            return true;
        } elseif (function_exists('json_encode')) {
            return true;
        }

        return false;
    }
}
