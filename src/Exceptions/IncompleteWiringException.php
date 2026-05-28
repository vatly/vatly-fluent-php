<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use RuntimeException;

/**
 * Thrown when {@see \Vatly\Fluent\Vatly} is asked for a service whose
 * required dependency was not provided to the {@see \Vatly\Fluent\Wiring} DTO.
 *
 * Examples:
 * - calling `Vatly::customers()` without a `customerBindings` impl in the wiring.
 * - calling `Vatly::webhookProcessor()` without an event dispatcher.
 *
 * The exception names the missing dependency and the feature being requested
 * so the fix is mechanical: add the dependency to your Wiring construction.
 */
final class IncompleteWiringException extends RuntimeException implements VatlyException
{
    public static function missing(string $dependency, string $forFeature): self
    {
        return new self(sprintf(
            "Vatly cannot resolve '%s' because the '%s' dependency was not provided in Wiring. "
            ."Pass it to `new Wiring(...)` when constructing `Vatly`.",
            $forFeature,
            $dependency,
        ));
    }
}
