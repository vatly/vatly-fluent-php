<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\Webhooks\WebhookPayload;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetRefund;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Events\OrderCanceled;
use Vatly\Fluent\Events\OrderChargebackReceived;
use Vatly\Fluent\Events\OrderChargebackReversed;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\PaymentFailed;
use Vatly\Fluent\Events\RefundCanceled;
use Vatly\Fluent\Events\RefundCompleted;
use Vatly\Fluent\Events\RefundFailed;
use Vatly\Fluent\Events\SubscriptionBillingUpdated;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionResumed;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;

class WebhookEventFactory
{
    public function __construct(
        private GetOrder $getOrder,
        private GetSubscription $getSubscription,
        private GetRefund $getRefund,
    ) {
        //
    }

    /**
     * Create a typed event from a raw webhook.
     *
     * For order-scoped events (`order.paid`, `payment.failed`) the factory
     * performs a follow-up API GET so the dispatched event carries the full
     * tax breakdown — webhook payloads themselves only include gross total.
     *
     * For `subscription.started` and `subscription.billing_updated` the same
     * enrichment pattern fetches the mandate summary so consumers can persist
     * the payment method on file without a separate API roundtrip per portal
     * render. Enrichment is best-effort: a transient `GetSubscription` failure
     * does not block persistence — the event falls back to the webhook payload,
     * which embeds the mandate inline, so the fallback stays non-lossy.
     *
     * For `refund.*` events the factory enriches via `GetRefund` for the same
     * reason as `order.paid`: refund tax data is compliance-critical and the
     * dispatched event must carry the authoritative breakdown.
     *
     * `order.canceled` and the `order.chargeback_*` events are built straight
     * from the webhook payload — a status mirror and dispute signals
     * respectively, neither of which needs enrichment.
     *
     * @return SubscriptionStarted|SubscriptionBillingUpdated|SubscriptionResumed|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|OrderPaid|OrderCanceled|OrderChargebackReceived|OrderChargebackReversed|PaymentFailed|RefundCompleted|RefundFailed|RefundCanceled|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        return match ($webhook->eventName) {
            SubscriptionStarted::VATLY_EVENT_NAME => $this->createSubscriptionStarted($webhook),
            SubscriptionBillingUpdated::VATLY_EVENT_NAME => $this->createSubscriptionBillingUpdated($webhook),
            SubscriptionResumed::VATLY_EVENT_NAME => SubscriptionResumed::fromWebhook($webhook),
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME => SubscriptionCanceledImmediately::fromWebhook($webhook),
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME => SubscriptionCanceledWithGracePeriod::fromWebhook($webhook),
            OrderPaid::VATLY_EVENT_NAME => $this->createOrderPaid($webhook),
            OrderCanceled::VATLY_EVENT_NAME => OrderCanceled::fromWebhook($webhook),
            OrderChargebackReceived::VATLY_EVENT_NAME => OrderChargebackReceived::fromWebhook($webhook),
            OrderChargebackReversed::VATLY_EVENT_NAME => OrderChargebackReversed::fromWebhook($webhook),
            PaymentFailed::VATLY_EVENT_NAME => $this->createPaymentFailed($webhook),
            RefundCompleted::VATLY_EVENT_NAME => RefundCompleted::fromApiRefund($this->getRefund->execute($webhook->entityId)),
            RefundFailed::VATLY_EVENT_NAME => RefundFailed::fromApiRefund($this->getRefund->execute($webhook->entityId)),
            RefundCanceled::VATLY_EVENT_NAME => RefundCanceled::fromApiRefund($this->getRefund->execute($webhook->entityId)),
            default => UnsupportedWebhookReceived::fromWebhook($webhook),
        };
    }

    /**
     * Build a SubscriptionStarted event, preferring the API-enriched form
     * (carries mandate) but falling back to the webhook payload on any
     * GetSubscription failure. Keeps the webhook flow resilient to
     * transient API errors that would otherwise force Vatly to retry the
     * delivery for a recoverable enrichment-only issue.
     */
    private function createSubscriptionStarted(WebhookReceived $webhook): SubscriptionStarted
    {
        try {
            return SubscriptionStarted::fromApiSubscription(
                $this->getSubscription->execute($webhook->entityId),
            );
        } catch (\Throwable) {
            return SubscriptionStarted::fromWebhook($webhook);
        }
    }

    /**
     * Build a SubscriptionBillingUpdated event, preferring the API-enriched
     * form (carries the fresh mandate) but falling back to the webhook payload
     * on any GetSubscription failure. Same resilience rationale as
     * {@see self::createSubscriptionStarted()}: the mandate refresh is an
     * enrichment, not a correctness requirement — a transient blip leaves the
     * stored mandate as-is rather than forcing Vatly to retry the delivery,
     * and the next `sync()` reconciles.
     */
    private function createSubscriptionBillingUpdated(WebhookReceived $webhook): SubscriptionBillingUpdated
    {
        try {
            return SubscriptionBillingUpdated::fromApiSubscription(
                $this->getSubscription->execute($webhook->entityId),
            );
        } catch (\Throwable) {
            return SubscriptionBillingUpdated::fromWebhook($webhook);
        }
    }

    /**
     * Build an OrderPaid event from the enriched API resource.
     *
     * Unlike SubscriptionStarted, OrderPaid intentionally rethrows on
     * enrichment failure: the webhook payload lacks `subtotal`, `taxSummary`,
     * and `status`. A best-effort fallback would persist a row with wrong tax
     * data, which compounds into compliance/reconciliation issues. Letting
     * the webhook fail forces Vatly to retry — the correct outcome for a
     * transient API blip, given that order data integrity beats availability
     * for payment-processing entities.
     */
    private function createOrderPaid(WebhookReceived $webhook): OrderPaid
    {
        return OrderPaid::fromApiOrder($this->getOrder->execute($webhook->entityId));
    }

    /**
     * Build a PaymentFailed event from the enriched API resource.
     *
     * Same rationale as {@see self::createOrderPaid()}: rethrows on enrichment
     * failure rather than writing a degraded row. Tax breakdown is critical
     * for dunning-notification accuracy and downstream reconciliation.
     */
    private function createPaymentFailed(WebhookReceived $webhook): PaymentFailed
    {
        return PaymentFailed::fromApiOrder($this->getOrder->execute($webhook->entityId));
    }

    /**
     * Build the framework-agnostic {@see WebhookReceived} from the upstream
     * {@see WebhookPayload}. The upstream `object` is a `stdClass`; we deep-
     * convert to an array so consumers keep using array access.
     */
    public function fromPayload(WebhookPayload $payload): WebhookReceived
    {
        $object = $payload->object !== null
            ? (array) json_decode((string) json_encode($payload->object), true)
            : [];

        return new WebhookReceived(
            id: $payload->id,
            resource: $payload->resource,
            eventName: $payload->eventName,
            entityType: $payload->entityType,
            entityId: $payload->entityId,
            testmode: $payload->testmode,
            createdAt: $payload->createdAt,
            object: $object,
        );
    }

    /**
     * Get the list of supported event names.
     *
     * @return array<string>
     */
    public function getSupportedEvents(): array
    {
        return [
            SubscriptionStarted::VATLY_EVENT_NAME,
            SubscriptionBillingUpdated::VATLY_EVENT_NAME,
            SubscriptionResumed::VATLY_EVENT_NAME,
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME,
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME,
            OrderPaid::VATLY_EVENT_NAME,
            OrderCanceled::VATLY_EVENT_NAME,
            OrderChargebackReceived::VATLY_EVENT_NAME,
            OrderChargebackReversed::VATLY_EVENT_NAME,
            PaymentFailed::VATLY_EVENT_NAME,
            RefundCompleted::VATLY_EVENT_NAME,
            RefundFailed::VATLY_EVENT_NAME,
            RefundCanceled::VATLY_EVENT_NAME,
        ];
    }

    /**
     * Check if an event name is supported.
     */
    public function isSupported(string $eventName): bool
    {
        return in_array($eventName, $this->getSupportedEvents(), true);
    }
}
