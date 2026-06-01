<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Types\CheckoutStatus;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Concerns\NormalizesWebhookMetadata;

/**
 * Event representing a hosted checkout canceled by the customer at Vatly.
 *
 * The customer abandoned the hosted checkout before completing payment — useful
 * for cart-abandonment funnel hooks. No order materializes, so `orderId` is
 * normally null.
 *
 * Built straight from the webhook payload — see {@see CheckoutPaid} for the
 * shared rationale (full Checkout resource on the wire, dispatched-only, no
 * built-in reaction).
 *
 * @immutable
 */
class CheckoutCanceled
{
    use NormalizesWebhookMetadata;

    public const VATLY_EVENT_NAME = WebhookEventName::CHECKOUT_CANCELED;

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
            status: $webhook->object['status'] ?? CheckoutStatus::STATUS_CANCELED,
            metadata: self::normalizeMetadata($webhook->object['metadata'] ?? null),
        );
    }
}
