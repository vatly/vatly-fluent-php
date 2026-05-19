<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

class SubscriptionCanceledImmediately
{
    public const VATLY_EVENT_NAME = 'subscription.canceled_immediately';

    public string $customerId;
    public string $subscriptionId;

    public function __construct(string $customerId, string $subscriptionId)
    {
        $this->customerId = $customerId;
        $this->subscriptionId = $subscriptionId;
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            $webhook->object['data']['customerId'],
            $webhook->resourceId
        );
    }
}
