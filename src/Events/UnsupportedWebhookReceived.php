<?php

declare(strict_types=1);

namespace Vatly\Events;

/**
 * Event representing an unsupported/unknown webhook event from Vatly.
 */
class UnsupportedWebhookReceived
{
    public function __construct(
        public readonly string $eventName,
        public readonly string $resourceId,
        public readonly string $resourceName,
        public readonly array $object,
        public readonly string $raisedAt,
        public readonly bool $testmode,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            eventName: $webhook->eventName,
            resourceId: $webhook->resourceId,
            resourceName: $webhook->resourceName,
            object: $webhook->object,
            raisedAt: $webhook->raisedAt,
            testmode: $webhook->testmode,
        );
    }
}
