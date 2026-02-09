<?php
declare(strict_types=1);

namespace Vatly\API\Exceptions;

class DebuggingNotSupportedException extends ApiException
{
    public static function new(): self
    {
        return new self("Debugging not supported by this http client.");
    }
}
