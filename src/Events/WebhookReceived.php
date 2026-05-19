<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a raw webhook call received from Vatly.
 *
 * @immutable
 */
class WebhookReceived
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
