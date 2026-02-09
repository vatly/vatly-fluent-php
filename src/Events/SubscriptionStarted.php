<?php

declare(strict_types=1);

namespace Vatly\Events;

/**
 * Event representing a subscription being started at Vatly.
 */
class SubscriptionStarted
{
    public const VATLY_EVENT_NAME = 'subscription.started';

    public const DEFAULT_TYPE = 'default';

    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
        public readonly string $planId,
        public readonly string $type,
        public readonly string $name,
        public readonly int $quantity,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['data']['customerId'],
            subscriptionId: $webhook->resourceId,
            planId: $webhook->object['data']['subscriptionPlanId'],
            type: self::DEFAULT_TYPE,
            name: $webhook->object['data']['name'],
            quantity: $webhook->object['data']['quantity'],
        );
    }
}
