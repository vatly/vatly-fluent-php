<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a previously-canceled subscription being resumed at Vatly.
 *
 * The inverse of the cancellation events: where
 * {@see SubscriptionCanceledImmediately} / {@see SubscriptionCanceledWithGracePeriod}
 * stamp an end date onto the local record,
 * {@see \Vatly\Fluent\Webhooks\Reactions\ResumeSubscriptionOnResumed} clears it,
 * re-activating the derived state. Carries no mandate/plan changes — resume
 * doesn't touch billing — so it's built straight from the webhook payload
 * without an enriching API roundtrip.
 *
 * @immutable
 */
class SubscriptionResumed
{
    public const VATLY_EVENT_NAME = 'subscription.resumed';

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['customerId'],
            subscriptionId: $webhook->entityId,
        );
    }
}
