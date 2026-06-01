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
 * The {@see \Vatly\Fluent\Webhooks\Reactions\EndSubscriptionOnGracePeriodCompleted}
 * reaction stamps the actual `endsAt` onto the local row. In the happy path the
 * cancellation already stamped the scheduled end (via
 * {@see \Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled}) so this
 * is an idempotent re-write, but it self-heals a missed/out-of-order
 * cancellation webhook (which would otherwise leave `endsAt` null and the
 * subscription looking active forever) and corrects any drift between the
 * scheduled and actual end. No driver-specific terminal status is synthesized —
 * Vatly has no `fully_ended` status to mirror — so this event is still
 * dispatched for consumers that want to flip such a flag of their own.
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
