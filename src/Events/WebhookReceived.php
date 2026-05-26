<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a raw webhook call received from Vatly.
 *
 * Mirrors the wire shape returned by `Vatly\API\Webhooks\Webhook::parse()`
 * — see {@see \Vatly\API\Webhooks\WebhookPayload}. The `object` field is
 * converted from the upstream stdClass to a deep array so consumers can
 * use array access (`$webhook->object['customerId']`) without juggling
 * property/array syntax.
 *
 * @immutable
 */
class WebhookReceived
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
        public array $object,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resource' => $this->resource,
            'eventName' => $this->eventName,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'object' => $this->object,
        ];
    }

    /**
     * Get the customer ID from the webhook payload, if present.
     */
    public function getCustomerId(): ?string
    {
        return $this->object['customerId'] ?? null;
    }
}
