<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing an unsupported/unknown webhook event from Vatly.
 *
 * @immutable
 */
class UnsupportedWebhookReceived
{
    /**
     * @param array<string, mixed> $object
     */
    public function __construct(
        public string $id,
        public string $resource,
        public string $eventName,
        public string $entityType,
        public string $entityId,
        public bool $testmode,
        public string $createdAt,
        public array $object,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            id: $webhook->id,
            resource: $webhook->resource,
            eventName: $webhook->eventName,
            entityType: $webhook->entityType,
            entityId: $webhook->entityId,
            testmode: $webhook->testmode,
            createdAt: $webhook->createdAt,
            object: $webhook->object,
        );
    }
}
