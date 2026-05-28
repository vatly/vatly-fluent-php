<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Order as ApiOrder;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Event representing an order being paid at Vatly.
 *
 * Carries the full tax breakdown so consumers can materialize a local invoice
 * without a follow-up API call. The webhook payload itself is sparse; the
 * WebhookEventFactory enriches via `GetOrder` before dispatching.
 *
 * @immutable
 */
class OrderPaid
{
    public const VATLY_EVENT_NAME = 'order.paid';

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
