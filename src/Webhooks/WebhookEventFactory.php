<?php

declare(strict_types=1);

namespace Vatly\Webhooks;

use Vatly\Events\SubscriptionCanceledImmediately;
use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Events\SubscriptionStarted;
use Vatly\Events\UnsupportedWebhookReceived;
use Vatly\Events\WebhookReceived;

class WebhookEventFactory
{
    /**
     * Create a typed event from a raw webhook.
     *
     * @return SubscriptionStarted|SubscriptionCanceledImmediately|SubscriptionCanceledWithGracePeriod|UnsupportedWebhookReceived
     */
    public function createFromWebhook(WebhookReceived $webhook): object
    {
        return match ($webhook->eventName) {
            SubscriptionStarted::VATLY_EVENT_NAME => SubscriptionStarted::fromWebhook($webhook),
            SubscriptionCanceledImmediately::VATLY_EVENT_NAME => SubscriptionCanceledImmediately::fromWebhook($webhook),
            SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME => SubscriptionCanceledWithGracePeriod::fromWebhook($webhook),
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
