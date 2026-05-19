<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a subscription being started at Vatly.
 *
 * @immutable
 */
class SubscriptionStarted
{
    public const VATLY_EVENT_NAME = 'subscription.started';

    public const DEFAULT_TYPE = 'default';

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public string $planId,
        public string $type,
        public string $name,
        public int $quantity,
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
