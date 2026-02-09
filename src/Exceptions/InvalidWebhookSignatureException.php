<?php

declare(strict_types=1);

namespace Vatly\Exceptions;

class InvalidWebhookSignatureException extends VatlyException
{
    public static function missingSignature(): self
    {
        return new self('Missing Vatly webhook signature.');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid Vatly webhook signature.');
    }
}
