<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\Webhooks\WebhookPayload;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;

class WebhookEventFactory
{
    public function __construct(
        private GetOrder $getOrder,
    ) {
        //
    }

    /**
     * Create a typed event from a raw webhook.
     *
     * For `order.paid` the factory performs a follow-up API GET so the
     * dispatched event carries the full tax breakdown — webhook payloads
     * themselves only include gross total.
     *
     * @return SubscriptionStarted|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|OrderPaid|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        return match ($webhook->eventName) {
            SubscriptionStarted::VATLY_EVENT_NAME => SubscriptionStarted::fromWebhook($webhook),
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME => SubscriptionCanceledImmediately::fromWebhook($webhook),
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME => SubscriptionCanceledWithGracePeriod::fromWebhook($webhook),
            OrderPaid::VATLY_EVENT_NAME => OrderPaid::fromApiOrder($this->getOrder->execute($webhook->entityId)),
            default => UnsupportedWebhookReceived::fromWebhook($webhook),
        };
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
