<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\Webhooks\WebhookPayload;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\PaymentFailed;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;

class WebhookEventFactory
{
    public function __construct(
        private GetOrder $getOrder,
        private GetSubscription $getSubscription,
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
     * For `subscription.started` the same enrichment pattern fetches the
     * mandate summary so consumers can persist the payment method on file
     * without a separate API roundtrip per portal render. Enrichment is
     * best-effort: a transient `GetSubscription` failure does not block
     * persistence — the event falls back to the webhook payload (mandate
     * stays null, backfilled by the next `sync()` or future
     * `subscription.billing_updated` event).
     *
     * @return SubscriptionStarted|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|OrderPaid|PaymentFailed|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        return match ($webhook->eventName) {
            SubscriptionStarted::VATLY_EVENT_NAME => $this->createSubscriptionStarted($webhook),
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME => SubscriptionCanceledImmediately::fromWebhook($webhook),
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME => SubscriptionCanceledWithGracePeriod::fromWebhook($webhook),
            OrderPaid::VATLY_EVENT_NAME => $this->createOrderPaid($webhook),
            PaymentFailed::VATLY_EVENT_NAME => $this->createPaymentFailed($webhook),
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
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME,
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME,
            OrderPaid::VATLY_EVENT_NAME,
            PaymentFailed::VATLY_EVENT_NAME,
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
