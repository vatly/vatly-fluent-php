<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use RuntimeException;

/**
 * Thrown when a host customer id is already bound to a Vatly customer
 * and the requested operation would silently overwrite that binding.
 *
 * `$attemptedVatlyCustomerId` is null when the throw site is
 * {@see \Vatly\Fluent\CustomerService::createFor()} — no Vatly customer was
 * proposed; the caller wanted fluent to create a new one. It is non-null
 * when the throw site is {@see \Vatly\Fluent\CustomerService::attribute()},
 * where the caller explicitly proposed a Vatly customer id to link.
 */
final class CustomerAlreadyBound extends RuntimeException implements VatlyException
{
    private function __construct(
        public readonly string $hostCustomerId,
        public readonly string $existingVatlyCustomerId,
        public readonly ?string $attemptedVatlyCustomerId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function onCreate(string $hostCustomerId, string $existingVatlyCustomerId): self
    {
        return new self(
            hostCustomerId:           $hostCustomerId,
            existingVatlyCustomerId:  $existingVatlyCustomerId,
            attemptedVatlyCustomerId: null,
            message: "Cannot create Vatly customer for host customer id '{$hostCustomerId}': already bound to Vatly customer '{$existingVatlyCustomerId}'.",
        );
    }

    public static function onAttribute(
        string $hostCustomerId,
        string $attemptedVatlyCustomerId,
        string $existingVatlyCustomerId,
    ): self {
        return new self(
            hostCustomerId:           $hostCustomerId,
            existingVatlyCustomerId:  $existingVatlyCustomerId,
            attemptedVatlyCustomerId: $attemptedVatlyCustomerId,
            message: "Cannot attribute Vatly customer '{$attemptedVatlyCustomerId}' to host customer id '{$hostCustomerId}': already bound to Vatly customer '{$existingVatlyCustomerId}'.",
        );
    }
}
