<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\WebhookEventName;
use Vatly\Fluent\Concerns\ParsesWebhookMandate;

/**
 * Event representing a subscription being started at Vatly.
 *
 * Built by {@see \Vatly\Fluent\Webhooks\WebhookEventFactory} via a follow-up
 * `GetSubscription` call against the webhook's `entityId` (carrying the mandate
 * summary). On a transient API failure it falls back to the webhook payload,
 * which itself embeds the mandate inline — so the fallback stays non-lossy.
 *
 * @immutable
 */
class SubscriptionStarted
{
    use ParsesWebhookMandate;

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
     * Webhook-payload-only build (the factory's fallback when `GetSubscription`
     * enrichment fails, and a convenience for tests). The mandate is read from
     * the embedded `object.mandate` when present, falling back to `null` only
     * when the payload genuinely carries none.
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
            mandate: self::mandateFromWebhookObject($webhook->object),
        );
    }
}
