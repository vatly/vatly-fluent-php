<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing an order being canceled at Vatly.
 *
 * The `order.canceled` webhook carries the full order payload including the
 * new `status` ("canceled"), so — unlike `order.paid` — no API enrichment is
 * needed: the built-in reaction only mirrors the status onto the local row.
 *
 * @immutable
 */
class OrderCanceled
{
    public const VATLY_EVENT_NAME = 'order.canceled';

    public function __construct(
        public string $customerId,
        public string $orderId,
        public string $status,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'] ?? '',
            orderId: $webhook->entityId,
            status: $webhook->object['status'] ?? 'canceled',
        );
    }
}
