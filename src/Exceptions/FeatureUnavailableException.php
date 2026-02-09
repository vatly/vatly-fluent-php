<?php

declare(strict_types=1);

namespace Vatly\Exceptions;

class FeatureUnavailableException extends VatlyException
{
    public static function notImplementedOnApi(): self
    {
        return new self('This feature is not available yet on the Vatly API.');
    }

    public static function notImplementedOnSdk(): self
    {
        return new self('This feature is not available yet on the Vatly SDK. Feel free to submit a PR.');
    }
}
