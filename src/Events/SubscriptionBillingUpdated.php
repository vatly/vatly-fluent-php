<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Concerns\ParsesWebhookMandate;

/**
 * Event representing a subscription's billing details — most importantly the
 * payment method on file — being updated at Vatly.
 *
 * Fired when a customer completes the hosted "update billing" flow (or the
 * mandate otherwise changes server-side). Built by
 * {@see \Vatly\Fluent\Webhooks\WebhookEventFactory} via a follow-up
 * `GetSubscription` call against the webhook's `entityId` (the fresh mandate
 * summary). On a transient API failure it falls back to the webhook payload,
 * which embeds the mandate inline — so the fallback stays non-lossy.
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
    use ParsesWebhookMandate;

    public const VATLY_EVENT_NAME = WebhookEventName::SUBSCRIPTION_BILLING_UPDATED;

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
     * Webhook-payload-only build, used when API enrichment fails. Reads the
     * mandate — the whole point of this event — from the embedded
     * `object.mandate` when present, so a transient `GetSubscription` failure
     * no longer drops the new payment method; it falls back to `null` only when
     * the payload carries no mandate, leaving the stored one untouched.
     */
    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'],
            subscriptionId: $webhook->entityId,
            planId: $webhook->object['subscriptionPlanId'],
            name: $webhook->object['name'],
            quantity: $webhook->object['quantity'],
            mandate: self::mandateFromWebhookObject($webhook->object),
        );
    }
}
