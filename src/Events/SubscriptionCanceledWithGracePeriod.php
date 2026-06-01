<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use DateTimeImmutable;
use DateTimeInterface;
use Vatly\API\Types\WebhookEventName;

/**
 * Event representing a subscription being canceled with a grace period at Vatly.
 *
 * @immutable
 */
class SubscriptionCanceledWithGracePeriod
{
    public const VATLY_EVENT_NAME = WebhookEventName::SUBSCRIPTION_CANCELED_WITH_GRACE_PERIOD;

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public DateTimeInterface $endsAt,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'],
            subscriptionId: $webhook->entityId,
            endsAt: new DateTimeImmutable($webhook->object['endedAt']),
        );
    }
}
