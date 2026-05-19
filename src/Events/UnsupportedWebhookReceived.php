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
        public string $eventName,
        public string $resourceId,
        public string $resourceName,
        public array $object,
        public string $raisedAt,
        public bool $testmode,
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
