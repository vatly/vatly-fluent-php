<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use DateTimeImmutable;
use DateTimeInterface;

class SubscriptionCanceledWithGracePeriod
{
    public const VATLY_EVENT_NAME = 'subscription.canceled_with_grace_period';

    public string $customerId;
    public string $subscriptionId;
    public DateTimeInterface $endsAt;

    public function __construct(string $customerId, string $subscriptionId, DateTimeInterface $endsAt)
    {
        $this->customerId = $customerId;
        $this->subscriptionId = $subscriptionId;
        $this->endsAt = $endsAt;
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            $webhook->object['data']['customerId'],
            $webhook->resourceId,
            new DateTimeImmutable($webhook->object['data']['endsAt'])
        );
    }
}
