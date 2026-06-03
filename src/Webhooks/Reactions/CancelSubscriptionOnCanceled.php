<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately;
use Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod;

/**
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
            || $event instanceof SubscriptionCanceledWithGracePeriod;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionCanceledImmediately
            && ! $event instanceof SubscriptionCanceledWithGracePeriod) {
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
