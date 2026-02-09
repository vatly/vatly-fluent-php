<?php

declare(strict_types=1);

namespace Vatly\Events;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Event representing a subscription being canceled with a grace period at Vatly.
 */
class SubscriptionCanceledWithGracePeriod
{
    public const VATLY_EVENT_NAME = 'subscription.canceled_with_grace_period';

    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
        public readonly DateTimeInterface $endsAt,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['data']['customerId'],
            subscriptionId: $webhook->resourceId,
            endsAt: new DateTimeImmutable($webhook->object['data']['endsAt']),
        );
    }
}
