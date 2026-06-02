<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Chargeback as ApiChargeback;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Event representing a previously-received chargeback being reversed at Vatly.
 *
 * Counterpart to {@see OrderChargebackReceived}: dispatched so drivers can
 * reinstate access they suspended on the original chargeback. The envelope's
 * `entityId` is the order ID.
 *
 * Enriched via `GetChargeback` when that action is wired (same rationale as
 * {@see OrderChargebackReceived}); otherwise built from the sparse webhook
 * payload. The built-in {@see \Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange}
 * uses the enriched status to flip the local chargeback row to its reversed
 * state.
 *
 * @immutable
 */
class OrderChargebackReversed
{
    public const VATLY_EVENT_NAME = WebhookEventName::ORDER_CHARGEBACK_REVERSED;

    public function __construct(
        public string $orderId,
        public string $chargebackId,
        public string $originalOrderId,
        public ?string $reason = null,
        public string $customerId = '',
        public string $status = '',
        public int $total = 0,
        public ?int $subtotal = null,
        public ?TaxSummary $taxSummary = null,
        public string $currency = '',
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

    public static function fromApiChargeback(ApiChargeback $chargeback): self
    {
        return new self(
            orderId: $chargeback->originalOrderId,
            chargebackId: $chargeback->id,
            originalOrderId: $chargeback->originalOrderId,
            reason: $chargeback->reason !== '' ? $chargeback->reason : null,
            customerId: $chargeback->customerId,
            status: $chargeback->status,
            total: Money::fromApiMoneyToCents($chargeback->total),
            subtotal: Money::fromApiMoneyToCents($chargeback->subtotal),
            taxSummary: TaxSummary::fromApiResource($chargeback->taxSummary),
            currency: $chargeback->total->currency,
        );
    }
}
