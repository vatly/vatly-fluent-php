<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

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
            OrderPaid::VATLY_EVENT_NAME => OrderPaid::fromApiOrder($this->getOrder->execute($webhook->resourceId)),
            default => UnsupportedWebhookReceived::fromWebhook($webhook),
        };
    }

    /**
     * Parse raw webhook payload into a WebhookReceived event.
     *
     * @param array<string, mixed> $payload
     */
    public function parsePayload(array $payload): WebhookReceived
    {
        return new WebhookReceived(
            eventName: $payload['eventName'] ?? '',
            resourceId: $payload['resourceId'] ?? '',
            resourceName: $payload['resourceName'] ?? '',
            object: $payload['object'] ?? [],
            raisedAt: $payload['raisedAt'] ?? '',
            testmode: $payload['testmode'] ?? false,
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
