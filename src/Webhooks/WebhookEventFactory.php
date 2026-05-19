<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;

class WebhookEventFactory
{
    /**
     * Create a typed event from a raw webhook.
     *
     * @return SubscriptionStarted|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|OrderPaid|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        switch ($webhook->eventName) {
            case SubscriptionStarted::VATLY_EVENT_NAME:
                return SubscriptionStarted::fromWebhook($webhook);
            case SubscriptionCanceledImmediately::VATLY_EVENT_NAME:
                return SubscriptionCanceledImmediately::fromWebhook($webhook);
            case SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME:
                return SubscriptionCanceledWithGracePeriod::fromWebhook($webhook);
            case OrderPaid::VATLY_EVENT_NAME:
                return OrderPaid::fromWebhook($webhook);
            default:
                return UnsupportedWebhookReceived::fromWebhook($webhook);
        }
    }

    /**
     * Parse raw webhook payload into a WebhookReceived event.
     *
     * @param array<string, mixed> $payload
     */
    public function parsePayload(array $payload): WebhookReceived
    {
        return new WebhookReceived(
            $payload['eventName'] ?? '',
            $payload['resourceId'] ?? '',
            $payload['resourceName'] ?? '',
            $payload['object'] ?? [],
            $payload['raisedAt'] ?? '',
            $payload['testmode'] ?? false
        );
    }

    /**
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

    public function isSupported(string $eventName): bool
    {
        return in_array($eventName, $this->getSupportedEvents(), true);
    }
}
