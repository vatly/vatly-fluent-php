<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Chargeback as ApiChargeback;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Event representing a chargeback being received against an order at Vatly.
 *
 * Dispatched so drivers can react — e.g. suspend access tied to the order and
 * open the dispute window. The envelope's `entityId` is the order ID, so the
 * driver can locate the affected order directly.
 *
 * When a `GetChargeback` action is wired, the {@see \Vatly\Fluent\Webhooks\WebhookEventFactory}
 * enriches this event via the API before dispatch (mirroring `order.paid` /
 * `refund.*`) so it carries the customer id, dispute status, and the full tax
 * breakdown — enough for the built-in
 * {@see \Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange} to
 * persist a local row and reconcile the reversed VAT without a second API call.
 * Without that action it degrades gracefully to the sparse webhook payload
 * (`orderId`, `chargebackId`, `originalOrderId`, `reason`).
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
