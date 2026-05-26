<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for webhook call persistence.
 */
interface WebhookCallRepositoryInterface
{
    /**
     * Record a webhook call.
     *
     * @param array<string, mixed> $object Raw `object` field from the webhook payload.
     */
    public function record(
        string $id,
        string $resource,
        string $eventName,
        string $entityType,
        string $entityId,
        array $object,
        ?string $vatlyCustomerId = null,
    ): void;

    /**
     * Clean up old webhook calls.
     */
    public function cleanUp(int $days = 7): int;
}
