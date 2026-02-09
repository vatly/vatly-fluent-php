<?php

declare(strict_types=1);

namespace Vatly\Events;

/**
 * Event representing a subscription being canceled immediately at Vatly.
 */
class SubscriptionCanceledImmediately
{
    public const VATLY_EVENT_NAME = 'subscription.canceled_immediately';

    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['data']['customerId'],
            subscriptionId: $webhook->resourceId,
        );
    }
}
