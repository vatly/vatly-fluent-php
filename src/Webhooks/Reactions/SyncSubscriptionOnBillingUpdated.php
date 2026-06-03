<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\SubscriptionBillingUpdated;

/**
 * Refreshes the locally-stored subscription when its billing details change
 * at Vatly — keeping the persisted mandate (card last-4, masked IBAN, …) in
 * step with the payment method actually on file.
 *
 * Unlike {@see SyncSubscriptionOnStarted} this never *creates* a local record:
 * a billing update for a subscription we've never seen means we missed its
 * `subscription.started`, and fabricating a half-known row from a
 * billing-update payload would be wrong. It mirrors
 * {@see CancelSubscriptionOnCanceled}'s find-or-skip stance instead.
 *
 * A null mandate on the event (the signed payload genuinely carried none) is
 * treated as "no mandate change" — `UpdateSubscriptionData`'s default — so a
 * payload without a mandate never wipes a good stored mandate; the next
 * `sync()` reconciles.
 *
 * @immutable
 */
class SyncSubscriptionOnBillingUpdated implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionBillingUpdated;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionBillingUpdated) {
            return;
        }

        $subscription = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $this->subscriptions->update($subscription, new UpdateSubscriptionData(
            planId: $event->planId,
            name: $event->name,
            quantity: $event->quantity,
            mandate: $event->mandate,
        ));
    }
}
