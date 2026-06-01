<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Event representing a failed payment attempt against an order at Vatly —
 * typically the start of dunning.
 *
 * The webhook is order-scoped; consumers that need to identify the affected
 * subscription should resolve it via their own customer/subscription map
 * (the same pattern as for renewal `order.paid`).
 *
 * Carries the full order shape (mirroring {@see OrderPaid}) so consumers can
 * surface failure details without a follow-up API call. The webhook payload
 * itself is sparse; the WebhookEventFactory enriches via `GetOrder`.
 *
 * @immutable
 */
class PaymentFailed
{
    public const VATLY_EVENT_NAME = WebhookEventName::PAYMENT_FAILED;

    public function __construct(
        public string $customerId,
        public string $orderId,
        public string $status,
        public int $total,
        public int $subtotal,
        public TaxSummary $taxSummary,
        public string $currency,
        public ?string $invoiceNumber,
        public ?string $paymentMethod,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {
        //
    }

    public static function fromApiOrder(ApiOrder $order): self
    {
        return new self(
            customerId: $order->customerId ?? '',
            orderId: $order->id,
            status: $order->status,
            total: Money::fromApiMoneyToCents($order->total),
            subtotal: Money::fromApiMoneyToCents($order->subtotal),
            taxSummary: TaxSummary::fromApiResource($order->taxSummary),
            currency: $order->total->currency,
            invoiceNumber: $order->invoiceNumber,
            paymentMethod: $order->paymentMethod,
            metadata: self::normalizeMetadata($order->metadata),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            /** @var array<string, mixed> $metadata */
            return $metadata;
        }

        if (is_object($metadata)) {
            /** @var array<string, mixed> $decoded */
            $decoded = (array) json_decode((string) json_encode($metadata), true);

            return $decoded;
        }

        return null;
    }
}
