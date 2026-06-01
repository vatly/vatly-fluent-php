<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Types\WebhookEventName;

/**
 * Event representing a chargeback being received against an order at Vatly.
 *
 * Vatly's public order status does not change on a chargeback, so fluent ships
 * no built-in reaction that mutates local state (mirroring Vatly rather than
 * synthesizing a status it never returns). Instead this typed event is
 * dispatched so drivers can react — e.g. suspend access tied to the order and
 * open the dispute window. The envelope's `entityId` is the order ID, so the
 * driver can locate the affected order directly.
 *
 * @immutable
 */
class OrderChargebackReceived
{
    public const VATLY_EVENT_NAME = WebhookEventName::ORDER_CHARGEBACK_RECEIVED;

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
