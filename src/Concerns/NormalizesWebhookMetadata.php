<?php

declare(strict_types=1);

namespace Vatly\Fluent\Concerns;

/**
 * Normalizes the free-form `metadata` bag off a webhook `object` payload into a
 * plain `array<string, mixed>` (or `null`).
 *
 * Vatly returns `metadata` as either a JSON object or `null`; once it has gone
 * through {@see \Vatly\Fluent\Webhooks\WebhookEventFactory::fromPayload()}'s
 * deep array conversion it is already an array, but an event built directly
 * from an API resource may still see a `stdClass`. This keeps consumers on a
 * single, predictable shape regardless of which path produced the event.
 */
trait NormalizesWebhookMetadata
{
    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            /** @var array<string, mixed> $metadata */
            return $metadata;
        }

        if (is_object($metadata)) {
            /** @var array<string, mixed> $decoded */
            $decoded = (array) json_decode((string) json_encode($metadata), true);

            return $decoded;
        }

        return null;
    }
}
