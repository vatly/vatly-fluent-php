<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a subscription being started at Vatly.
 */
class SubscriptionStarted
{
    public const VATLY_EVENT_NAME = 'subscription.started';

    public const DEFAULT_TYPE = 'default';

    public string $customerId;
    public string $subscriptionId;
    public string $planId;
    public string $type;
    public string $name;
    public int $quantity;

    public function __construct(
        string $customerId,
        string $subscriptionId,
        string $planId,
        string $type,
        string $name,
        int $quantity
    ) {
        $this->customerId = $customerId;
        $this->subscriptionId = $subscriptionId;
        $this->planId = $planId;
        $this->type = $type;
        $this->name = $name;
        $this->quantity = $quantity;
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            $webhook->object['data']['customerId'],
            $webhook->resourceId,
            $webhook->object['data']['subscriptionPlanId'],
            self::DEFAULT_TYPE,
            $webhook->object['data']['name'],
            $webhook->object['data']['quantity']
        );
    }
}
