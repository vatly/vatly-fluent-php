<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

class InvalidOrderException extends VatlyException
{
    public static function notFound(string $vatlyId): self
    {
        return new self("No order found with Vatly order ID: {$vatlyId}");
    }

    public static function notOwnedBy(string $vatlyId, object $owner): self
    {
        $class = get_class($owner);
        $shortClass = substr($class, strrpos($class, '\\') + 1);

        return new self("Order {$vatlyId} is not owned by the given {$shortClass}.");
    }
}
