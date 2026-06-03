<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\Chargeback as ApiChargeback;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Resources\Refund as ApiRefund;
use Vatly\API\Resources\ResourceFactory;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\VatlyApiClient;
use Vatly\API\Webhooks\WebhookPayload;
use Vatly\API\Webhooks\Events\CheckoutCanceled;
use Vatly\API\Webhooks\Events\CheckoutExpired;
use Vatly\API\Webhooks\Events\CheckoutFailed;
use Vatly\API\Webhooks\Events\CheckoutPaid;
use Vatly\API\Webhooks\Events\OrderCanceled;
use Vatly\API\Webhooks\Events\OrderChargebackReceived;
use Vatly\API\Webhooks\Events\OrderChargebackReversed;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;
use Vatly\API\Webhooks\Events\RefundCanceled;
use Vatly\API\Webhooks\Events\RefundCompleted;
use Vatly\API\Webhooks\Events\RefundFailed;
use Vatly\API\Webhooks\Events\SubscriptionBillingUpdated;
use Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately;
use Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\API\Webhooks\Events\SubscriptionCancellationGracePeriodCompleted;
use Vatly\API\Webhooks\Events\SubscriptionResumed;
use Vatly\API\Webhooks\Events\SubscriptionStarted;
use Vatly\API\Webhooks\Events\UnsupportedWebhookReceived;
use Vatly\API\Webhooks\Events\WebhookReceived;
use Vatly\API\Webhooks\Events\WebhookSetupReceived;

/**
 * Turns a raw Vatly webhook into a typed event.
 *
 * Vatlify sends **fat, HMAC-signed** webhook payloads: the delivery's `object`
 * is the full resource — byte-identical to the corresponding `GET /…/{id}` body
 * (subtotal, the complete tax summary, lines, mandate, …). The HMAC signature
 * (verified upstream in {@see WebhookProcessor}) is the trust boundary, so the
 * payload is the authoritative snapshot.
 *
 * That means there is no follow-up API GET. For the money/tax-bearing events
 * the factory hydrates the matching api-php Resource straight from
 * `$webhook->object` (via {@see ResourceFactory::createResourceFromApiResult()},
 * the same path the API client uses to build a resource from a `GET` response)
 * and maps it through the event's `fromApi*` constructor — same enriched event,
 * zero network round-trip. The hydrating accessors used by the mappers
 * (e.g. {@see ApiOrder::lines()}) read the embedded data and never touch HTTP.
 *
 * The status-mirror and checkout events (`order.canceled`, `checkout.*`,
 * `subscription.*` cancellation transitions) are built straight from the
 * payload envelope; they carry no money/tax fields to hydrate.
 */
class WebhookEventFactory
{
    public function __construct(
        private VatlyApiClient $apiClient,
    ) {
        //
    }

    /**
     * Create a typed event from a raw webhook.
     *
     * Every event is built from the signed webhook payload — the money/tax
     * bearing ones by hydrating the api-php Resource from `$webhook->object`,
     * the rest straight from the envelope. No follow-up API call is made.
     *
     * @return SubscriptionStarted|SubscriptionBillingUpdated|SubscriptionResumed|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|SubscriptionCancellationGracePeriodCompleted|OrderPaid|OrderCanceled|OrderChargebackReceived|OrderChargebackReversed|OrderPaymentFailed|CheckoutPaid|CheckoutFailed|CheckoutCanceled|CheckoutExpired|RefundCompleted|RefundFailed|RefundCanceled|WebhookSetupReceived|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        return match ($webhook->eventName) {
            SubscriptionStarted::VATLY_EVENT_NAME => SubscriptionStarted::fromApiSubscription($this->hydrateSubscription($webhook)),
            SubscriptionBillingUpdated::VATLY_EVENT_NAME => SubscriptionBillingUpdated::fromApiSubscription($this->hydrateSubscription($webhook)),
            SubscriptionResumed::VATLY_EVENT_NAME => SubscriptionResumed::fromWebhook($webhook),
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME => SubscriptionCanceledImmediately::fromWebhook($webhook),
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME => SubscriptionCanceledWithGracePeriod::fromWebhook($webhook),
            SubscriptionCancellationGracePeriodCompleted::VATLY_EVENT_NAME => SubscriptionCancellationGracePeriodCompleted::fromWebhook($webhook),
            OrderPaid::VATLY_EVENT_NAME => OrderPaid::fromApiOrder($this->hydrateOrder($webhook)),
            OrderCanceled::VATLY_EVENT_NAME => OrderCanceled::fromWebhook($webhook),
            OrderChargebackReceived::VATLY_EVENT_NAME => OrderChargebackReceived::fromApiChargeback($this->hydrateChargeback($webhook)),
            OrderChargebackReversed::VATLY_EVENT_NAME => OrderChargebackReversed::fromApiChargeback($this->hydrateChargeback($webhook)),
            OrderPaymentFailed::VATLY_EVENT_NAME => OrderPaymentFailed::fromApiOrder($this->hydrateOrder($webhook)),
            CheckoutPaid::VATLY_EVENT_NAME => CheckoutPaid::fromWebhook($webhook),
            CheckoutFailed::VATLY_EVENT_NAME => CheckoutFailed::fromWebhook($webhook),
            CheckoutCanceled::VATLY_EVENT_NAME => CheckoutCanceled::fromWebhook($webhook),
            CheckoutExpired::VATLY_EVENT_NAME => CheckoutExpired::fromWebhook($webhook),
            RefundCompleted::VATLY_EVENT_NAME => RefundCompleted::fromApiRefund($this->hydrateRefund($webhook)),
            RefundFailed::VATLY_EVENT_NAME => RefundFailed::fromApiRefund($this->hydrateRefund($webhook)),
            RefundCanceled::VATLY_EVENT_NAME => RefundCanceled::fromApiRefund($this->hydrateRefund($webhook)),
            WebhookSetupReceived::VATLY_EVENT_NAME => WebhookSetupReceived::fromWebhook($webhook),
            default => UnsupportedWebhookReceived::fromWebhook($webhook),
        };
    }

    private function hydrateOrder(WebhookReceived $webhook): ApiOrder
    {
        $order = $this->hydrate($webhook, new ApiOrder($this->apiClient));
        assert($order instanceof ApiOrder);

        return $order;
    }

    private function hydrateSubscription(WebhookReceived $webhook): ApiSubscription
    {
        $subscription = $this->hydrate($webhook, new ApiSubscription($this->apiClient));
        assert($subscription instanceof ApiSubscription);

        return $subscription;
    }

    private function hydrateRefund(WebhookReceived $webhook): ApiRefund
    {
        $refund = $this->hydrate($webhook, new ApiRefund($this->apiClient));
        assert($refund instanceof ApiRefund);

        return $refund;
    }

    private function hydrateChargeback(WebhookReceived $webhook): ApiChargeback
    {
        $chargeback = $this->hydrate($webhook, new ApiChargeback($this->apiClient));
        assert($chargeback instanceof ApiChargeback);

        return $chargeback;
    }

    /**
     * Hydrate an api-php Resource from the signed webhook payload.
     *
     * {@see ResourceFactory::createResourceFromApiResult()} expects the same
     * `stdClass` tree the API client decodes a `GET` response into, so we
     * re-encode the (deep-array) `$webhook->object` back to objects before
     * handing it over. No HTTP is performed — this is a pure in-memory map.
     */
    private function hydrate(WebhookReceived $webhook, BaseResource $resource): BaseResource
    {
        $apiResult = json_decode((string) json_encode($webhook->object), false);

        if (! $apiResult instanceof \stdClass) {
            $apiResult = new \stdClass();
        }

        return ResourceFactory::createResourceFromApiResult($apiResult, $resource);
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
            SubscriptionCancellationGracePeriodCompleted::VATLY_EVENT_NAME,
            OrderPaid::VATLY_EVENT_NAME,
            OrderCanceled::VATLY_EVENT_NAME,
            OrderChargebackReceived::VATLY_EVENT_NAME,
            OrderChargebackReversed::VATLY_EVENT_NAME,
            OrderPaymentFailed::VATLY_EVENT_NAME,
            CheckoutPaid::VATLY_EVENT_NAME,
            CheckoutFailed::VATLY_EVENT_NAME,
            CheckoutCanceled::VATLY_EVENT_NAME,
            CheckoutExpired::VATLY_EVENT_NAME,
            RefundCompleted::VATLY_EVENT_NAME,
            RefundFailed::VATLY_EVENT_NAME,
            RefundCanceled::VATLY_EVENT_NAME,
            WebhookSetupReceived::VATLY_EVENT_NAME,
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
