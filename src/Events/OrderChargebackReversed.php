<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a previously-received chargeback being reversed at Vatly.
 *
 * Counterpart to {@see OrderChargebackReceived}: dispatched so drivers can
 * reinstate access they suspended on the original chargeback. As with the
 * received event, Vatly's public order status is unchanged, so fluent ships no
 * built-in state-mutating reaction. The envelope's `entityId` is the order ID.
 *
 * @immutable
 */
class OrderChargebackReversed
{
    public const VATLY_EVENT_NAME = 'order.chargeback_reversed';

    public function __construct(
        public string $orderId,
        public string $chargebackId,
        public string $originalOrderId,
        public ?string $reason = null,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            orderId: $webhook->entityId,
            chargebackId: $webhook->object['id'] ?? '',
            originalOrderId: $webhook->object['originalOrderId'] ?? $webhook->entityId,
            reason: $webhook->object['reason'] ?? null,
        );
    }
}
