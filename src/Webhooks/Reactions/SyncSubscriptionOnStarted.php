<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreSubscriptionData;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Events\LocalSubscriptionCreated;
use Vatly\Fluent\Events\SubscriptionStarted;

/**
 * @immutable
 */
class SyncSubscriptionOnStarted implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionStarted;
    }

    public function handle(object $event): void
    {
        /** @var SubscriptionStarted $event */
        $existing = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($existing !== null) {
            $this->subscriptions->update($existing, new UpdateSubscriptionData(
                planId: $event->planId,
                name: $event->name,
                quantity: $event->quantity,
            ));

            return;
        }

        $subscription = $this->subscriptions->store(new StoreSubscriptionData(
            vatlyId: $event->subscriptionId,
            customerId: $event->customerId,
            type: $event->type,
            planId: $event->planId,
            name: $event->name,
            quantity: $event->quantity,
        ));

        $this->dispatcher->dispatch(new LocalSubscriptionCreated($subscription));
    }
}
