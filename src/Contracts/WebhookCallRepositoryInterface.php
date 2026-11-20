<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use DateTimeInterface;

/**
 * Interface for webhook call persistence.
 */
interface WebhookCallRepositoryInterface
{
    /**
     * Record a webhook call.
     *
     * @param array<string, mixed> $object The webhook payload's `object` field, with the string values
     *                                    of its personal fields redacted
     *                                    ({@see \Vatly\Fluent\Webhooks\WebhookCallRedactor}).
     */
    public function record(
        string $id,
        string $resource,
        string $eventName,
        string $entityType,
        string $entityId,
        bool $testmode,
        DateTimeInterface $createdAt,
        array $object,
        ?string $vatlyCustomerId = null,
    ): void;

    /**
     * Clean up old webhook calls.
     */
    public function cleanUp(int $days = 7): int;
}
