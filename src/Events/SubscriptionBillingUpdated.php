<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;

/**
 * Event representing a subscription's billing details — most importantly the
 * payment method on file — being updated at Vatly.
 *
 * Fired when a customer completes the hosted "update billing" flow (or the
 * mandate otherwise changes server-side). Built by
 * {@see \Vatly\Fluent\Webhooks\WebhookEventFactory} via a follow-up
 * `GetSubscription` call against the webhook's `entityId`, so the dispatched
 * event carries the fresh mandate summary that isn't on the webhook payload.
 *
 * This is the companion to {@see SubscriptionStarted}: `started` captures the
 * initial mandate, this event keeps it current. Without it, a mandate
 * persisted at subscription start goes stale the moment the customer switches
 * cards or payment methods.
 *
 * @immutable
 */
class SubscriptionBillingUpdated
{
    public const VATLY_EVENT_NAME = 'subscription.billing_updated';

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public string $planId,
        public string $name,
        public int $quantity,
        /**
         * Payment method on file after the update. `null` when enrichment
         * fell back to the webhook payload (which doesn't carry the mandate)
         * — in that case {@see \Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnBillingUpdated}
         * leaves the stored mandate untouched rather than wiping it, and the
         * next `sync()` backfills the change. The mandate's method +
         * maskedIdentifier are atomically bound together inside the Mandate
         * object so listeners can't mix old + new values.
         */
        public ?Mandate $mandate = null,
    ) {
        //
    }

    /**
     * Build from the enriched API resource fetched by
     * {@see \Vatly\Fluent\Webhooks\WebhookEventFactory::createFromWebhook()}.
     */
    public static function fromApiSubscription(ApiSubscription $subscription): self
    {
        return new self(
            customerId: $subscription->customerId ?? '',
            subscriptionId: $subscription->id,
            planId: $subscription->subscriptionPlanId,
            name: $subscription->name,
            quantity: $subscription->quantity,
            mandate: $subscription->mandate,
        );
    }

    /**
     * Sparse, webhook-payload-only build used when API enrichment fails.
     * Mandate stays null — the whole point of this event — so the reaction
     * deliberately makes no mandate change and defers to the next sync().
     */
    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'],
            subscriptionId: $webhook->entityId,
            planId: $webhook->object['subscriptionPlanId'],
            name: $webhook->object['name'],
            quantity: $webhook->object['quantity'],
        );
    }
}
