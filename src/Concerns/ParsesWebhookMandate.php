<?php

declare(strict_types=1);

namespace Vatly\Fluent\Concerns;

use Vatly\API\Types\Mandate;

/**
 * Parses a {@see Mandate} out of a webhook `object` payload when it's present.
 *
 * Vatly embeds the mandate inline on subscription deliveries
 * (`object.mandate.method` / `object.mandate.maskedIdentifier`). The event
 * factory prefers an API-enriched mandate, but on a `GetSubscription` failure it
 * falls back to the webhook payload — and this keeps that fallback non-lossy by
 * reading the embedded mandate instead of dropping it.
 *
 * Defensive: returns `null` unless `object.mandate` is an array carrying a
 * string `method`, so a missing/`null`/malformed mandate never throws.
 */
trait ParsesWebhookMandate
{
    /**
     * @param array<string, mixed> $object
     */
    private static function mandateFromWebhookObject(array $object): ?Mandate
    {
        $mandate = $object['mandate'] ?? null;

        if (! is_array($mandate) || ! isset($mandate['method']) || ! is_string($mandate['method'])) {
            return null;
        }

        return Mandate::createResourceFromApiResult($mandate);
    }
}
