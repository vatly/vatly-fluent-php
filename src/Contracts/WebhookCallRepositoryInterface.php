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
     * @param array<string, mixed> $payload
     */
    public function record(
        string $eventName,
        string $resourceId,
        string $resourceName,
        array $payload,
        DateTimeInterface $raisedAt,
        bool $testmode,
        ?string $vatlyCustomerId = null
    ): void;

    /**
     * Clean up old webhook calls.
     */
    public function cleanUp(int $days = 7): int;
}
