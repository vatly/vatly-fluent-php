<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\WebhookEventName;

/**
 * Event representing a subscription being started at Vatly.
 *
 * Built by {@see \Vatly\Fluent\Webhooks\WebhookEventFactory} via a follow-up
 * `GetSubscription` call against the webhook's `entityId`, so the dispatched
 * event carries the mandate summary that isn't on the webhook payload.
 *
 * @immutable
 */
class SubscriptionStarted
{
    public const VATLY_EVENT_NAME = WebhookEventName::SUBSCRIPTION_STARTED;

    public const DEFAULT_TYPE = 'default';

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public string $planId,
        public string $type,
        public string $name,
        public int $quantity,
        /**
         * Payment method on file at the time the webhook fires. `null` when
         * no mandate has bound yet — the API briefly returns `mandate: null`
         * for freshly-subscribed customers (see
         * {@see \Vatly\API\Types\Mandate::$maskedIdentifier}'s docblock).
         * The mandate's method + maskedIdentifier are atomically bound
         * together inside the Mandate object so listeners can't accidentally
         * mix old + new values.
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
            type: self::DEFAULT_TYPE,
            name: $subscription->name,
            quantity: $subscription->quantity,
            mandate: $subscription->mandate,
        );
    }

    /**
     * Sparse, webhook-payload-only build kept for tests and callers who don't
     * want to fetch the full API resource. Mandate stays null.
     */
    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'],
            subscriptionId: $webhook->entityId,
            planId: $webhook->object['subscriptionPlanId'],
            type: self::DEFAULT_TYPE,
            name: $webhook->object['name'],
            quantity: $webhook->object['quantity'],
        );
    }
}
