<?php

declare(strict_types=1);

namespace Vatly\Exceptions;

class IncompleteInformationException extends VatlyException
{
    public static function noCheckoutItems(): self
    {
        return new self('No checkout items provided. At least one item should be set when creating a checkout.');
    }
}
