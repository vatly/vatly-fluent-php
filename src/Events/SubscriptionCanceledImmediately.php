<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Event representing a subscription being canceled immediately at Vatly.
 *
 * @immutable
 */
class SubscriptionCanceledImmediately
{
    public const VATLY_EVENT_NAME = 'subscription.canceled_immediately';

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public DateTimeInterface $endsAt,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        $endsAt = $webhook->object['data']['endsAt'] ?? null;

        return new self(
            customerId: $webhook->object['data']['customerId'],
            subscriptionId: $webhook->entityId,
            endsAt: $endsAt !== null ? new DateTimeImmutable($endsAt) : new DateTimeImmutable(),
        );
    }
}
