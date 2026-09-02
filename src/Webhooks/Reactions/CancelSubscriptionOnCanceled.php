<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\SubscriptionCanceledForNonpayment;
use Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately;
use Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod;

/**
 * Ends the local subscription (persisting the event's `endsAt` and
 * `cancellationReason`) on any hard or grace-period cancellation:
 * merchant-initiated ({@see SubscriptionCanceledImmediately},
 * {@see SubscriptionCanceledWithGracePeriod}) and nonpayment-initiated
 * ({@see SubscriptionCanceledForNonpayment}, a hard cancellation after payment
 * recovery is exhausted). All three events expose a `cancellationReason`
 * (`merchant_request` / `customer_request` / `payment_failure`).
 *
 * @immutable
 */
class CancelSubscriptionOnCanceled implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionCanceledImmediately
            || $event instanceof SubscriptionCanceledWithGracePeriod
            || $event instanceof SubscriptionCanceledForNonpayment;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionCanceledImmediately
            && ! $event instanceof SubscriptionCanceledWithGracePeriod
            && ! $event instanceof SubscriptionCanceledForNonpayment) {
            return;
        }

        $subscription = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($subscription === null) {
            return;
        }

        // All three cancel events expose `cancellationReason` (merchant_request /
        // customer_request / payment_failure), so read it uniformly.
        $this->subscriptions->update($subscription, new UpdateSubscriptionData(
            endsAt: $event->endsAt,
            cancellationReason: $event->cancellationReason,
        ));
    }
}
