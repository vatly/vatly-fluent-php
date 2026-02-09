<?php

declare(strict_types=1);

namespace Vatly\Events;

/**
 * Event representing a raw webhook call received from Vatly.
 */
class WebhookReceived
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventName' => $this->eventName,
            'resourceId' => $this->resourceId,
            'resourceName' => $this->resourceName,
            'object' => $this->object,
            'raisedAt' => $this->raisedAt,
            'testmode' => $this->testmode,
        ];
    }

    /**
     * Get the customer ID from the webhook payload, if present.
     */
    public function getCustomerId(): ?string
    {
        return $this->object['data']['customerId'] ?? null;
    }
}
