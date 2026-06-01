<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Events\SubscriptionCancellationGracePeriodCompleted;

/**
 * Stamps the actual end date onto the local subscription when its cancellation
 * grace period has run out at Vatly.
 *
 * The terminal companion to {@see CancelSubscriptionOnCanceled}: that stamps the
 * *scheduled* `endsAt` when the cancellation arrives; this stamps the *actual*
 * `endsAt` the grace period completed with. In the happy path the two coincide
 * and this is a harmless idempotent re-write, but it earns its keep in two cases
 * the cancellation reaction alone can't cover:
 *
 *  - **Self-healing a missed cancellation.** If the `subscription.canceled_*`
 *    webhook was never delivered (webhooks can be dropped or arrive out of
 *    order), the local row still has `endsAt = null`, so every
 *    {@see \Vatly\Fluent\Concerns\DerivesSubscriptionState} predicate reports
 *    the subscription as active *forever*. This event is the last signal Vatly
 *    sends for the cancellation lifecycle; stamping `endsAt` here flips the
 *    derived state to ended, as it should be.
 *  - **Correcting drift.** The originally-stamped `endsAt` was the scheduled
 *    grace end; if it shifted upstream (grace extended/shortened), the event's
 *    `endsAt` is the authoritative actual end.
 *
 * It writes only the `endsAt` column every other subscription reaction already
 * manages — no driver-specific terminal status is synthesized (Vatly has none
 * to mirror), so drivers wanting a `fully_ended` flag still listen for the
 * dispatched {@see SubscriptionCancellationGracePeriodCompleted} event.
 *
 * Find-or-skip: a grace-period completion for an untracked subscription is a
 * no-op.
 *
 * @immutable
 */
class EndSubscriptionOnGracePeriodCompleted implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionCancellationGracePeriodCompleted;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionCancellationGracePeriodCompleted) {
            return;
        }

        $subscription = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $this->subscriptions->update($subscription, new UpdateSubscriptionData(
            endsAt: $event->endsAt,
        ));
    }
}
