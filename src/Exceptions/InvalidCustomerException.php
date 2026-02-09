<?php

declare(strict_types=1);

namespace Vatly\Exceptions;

class InvalidCustomerException extends VatlyException
{
    public static function notYetCreated(object $owner): self
    {
        $class = get_class($owner);
        $shortClass = substr($class, strrpos($class, '\\') + 1);

        return new self("{$shortClass} is not a Vatly customer yet. See the createAsVatlyCustomer method.");
    }

    public static function notFound(string $vatlyId): self
    {
        return new self("No billable found with Vatly customer ID: {$vatlyId}");
    }
}
