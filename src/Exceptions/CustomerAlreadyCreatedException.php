<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use Vatly\Fluent\Contracts\BillableInterface;

class CustomerAlreadyCreatedException extends VatlyException
{
    public static function exists(BillableInterface $owner): self
    {
        $class = get_class($owner);
        $shortClass = substr($class, strrpos($class, '\\') + 1);

        return new self("{$shortClass} is already a Vatly customer with ID {$owner->getVatlyId()}.");
    }
}
