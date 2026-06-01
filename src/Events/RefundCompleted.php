<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Refund as ApiRefund;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Event representing a refund completing (funds returned to the customer) at Vatly.
 *
 * Carries the full tax breakdown so consumers can reconcile a local refund row
 * without a follow-up API call. The {@see \Vatly\Fluent\Webhooks\WebhookEventFactory}
 * enriches via `GetRefund` before dispatching — mirroring the `order.paid`
 * pattern, since refund tax data is compliance-critical and must be authoritative.
 *
 * @immutable
 */
class RefundCompleted
{
    public const VATLY_EVENT_NAME = WebhookEventName::REFUND_COMPLETED;

    public function __construct(
        public string $customerId,
        public string $refundId,
        public string $status,
        public int $total,
        public int $subtotal,
        public TaxSummary $taxSummary,
        public string $currency,
        public string $originalOrderId,
    ) {
        //
    }

    public static function fromApiRefund(ApiRefund $refund): self
    {
        return new self(
            customerId: $refund->customerId,
            refundId: $refund->id,
            status: $refund->status,
            total: Money::fromApiMoneyToCents($refund->total),
            subtotal: Money::fromApiMoneyToCents($refund->subtotal),
            taxSummary: TaxSummary::fromApiResource($refund->taxSummary),
            currency: $refund->total->currency,
            originalOrderId: $refund->originalOrderId,
        );
    }
}
