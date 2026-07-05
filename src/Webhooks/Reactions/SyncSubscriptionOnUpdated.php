<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\API\Webhooks\Events\SubscriptionUpdated;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;

/**
 * Refreshes the locally-stored subscription when it changes **immediately** at
 * Vatly — a plan / price / interval / quantity change applied right away (the
 * `subscription.updated` event).
 *
 * Its scheduled counterpart, `subscription.update_scheduled`, is *not* handled
 * here: that change has not taken effect yet (it applies at the next billing
 * cycle), so mutating the local row would misreport the subscription's current
 * state. That event is dispatched-only — a driver can react to it (e.g. to warn
 * the customer of an upcoming price change) via its own listener.
 *
 * Like {@see SyncSubscriptionOnBillingUpdated} this is find-or-skip: it updates
 * an existing local record but never creates one — an update for a subscription
 * we've never seen means we missed its `subscription.started`, and fabricating a
 * half-known row from an update payload would be wrong. Price is not persisted
 * locally (fluent's `Store*Data` DTOs track plan/name/quantity, not the money),
 * so only the fields the driver stores are pushed through.
 *
 * @immutable
 */
class SyncSubscriptionOnUpdated implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionUpdated;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionUpdated) {
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
        ));
    }
}
