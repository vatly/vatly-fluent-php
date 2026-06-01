<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Types\CheckoutStatus;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Concerns\NormalizesWebhookMetadata;

/**
 * Event representing a hosted checkout being paid at Vatly.
 *
 * Fires at the earliest "customer paid" moment — before `order.paid`'s
 * tax-summary enrichment GET — so drivers using the hosted-checkout flow can
 * drive in-app "thanks" / receipt UI and analytics handoff without waiting for
 * the order to materialize. It overlaps semantically with {@see OrderPaid} but
 * is scoped to the checkout, not the order it produces; the `orderId` links the
 * two once the order exists.
 *
 * Built straight from the webhook payload: the `checkout.*` deliveries carry the
 * full Checkout resource (status, customerId, orderId, metadata) with no sparse
 * money/tax fields that would need a follow-up API GET — unlike orders and
 * subscriptions. fluent ships no built-in reaction; this is a dispatched-only
 * signal drivers consume for receipts/analytics.
 *
 * `customerId` is nullable: an anonymous checkout only gets a customer
 * associated once payment completes, so it is normally present here but the
 * type stays honest rather than synthesizing an empty string.
 *
 * @immutable
 */
class CheckoutPaid
{
    use NormalizesWebhookMetadata;

    public const VATLY_EVENT_NAME = WebhookEventName::CHECKOUT_PAID;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $checkoutId,
        public ?string $customerId,
        public ?string $orderId,
        public string $status,
        public ?array $metadata = null,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            checkoutId: $webhook->entityId,
            customerId: $webhook->object['customerId'] ?? null,
            orderId: $webhook->object['orderId'] ?? null,
            status: $webhook->object['status'] ?? CheckoutStatus::STATUS_PAID,
            metadata: self::normalizeMetadata($webhook->object['metadata'] ?? null),
        );
    }
}
