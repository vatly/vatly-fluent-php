<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use DateTimeImmutable;
use DateTimeInterface;
use Vatly\API\Types\WebhookEventName;

/**
 * Event representing a canceled subscription whose grace period has now run out.
 *
 * The terminal transition after {@see SubscriptionCanceledWithGracePeriod}: the
 * grace-period clock reached `endsAt` and the subscription is now fully ended.
 * Today drivers infer this by polling `endsAt < now` on a scheduled job; this
 * direct event lets local state flip atomically and removes a class of
 * "the cron is late so the state is wrong" bugs.
 *
 * fluent ships no built-in reaction: the cancellation that scheduled the grace
 * period already stamped `endsAt` onto the local row (via
 * {@see \Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled}), so the
 * derived "ended" state is already correct once the clock passes. Whether to
 * additionally flip a stored status to a `fully_ended` value is driver-specific
 * — there is no such status in Vatly's own model to mirror — so this is
 * dispatched-only, leaving that decision to the consumer.
 *
 * @immutable
 */
class SubscriptionCancellationGracePeriodCompleted
{
    public const VATLY_EVENT_NAME = WebhookEventName::SUBSCRIPTION_CANCELLATION_GRACE_PERIOD_COMPLETED;

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
            customerId: $webhook->object['customerId'] ?? '',
            subscriptionId: $webhook->entityId,
            endsAt: new DateTimeImmutable($webhook->object['endedAt'] ?? $webhook->createdAt),
        );
    }
}
