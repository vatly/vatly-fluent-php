<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Events\SubscriptionStarted;

class SyncSubscriptionOnStarted implements WebhookReactionInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
    ) {
        //
    }

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionStarted;
    }

    public function handle(object $event): void
    {
        /** @var SubscriptionStarted $event */
        $existing = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($existing !== null) {
            $this->subscriptions->update($existing, [
                'plan_id' => $event->planId,
                'name' => $event->name,
                'quantity' => $event->quantity,
            ]);

            return;
        }

        $this->subscriptions->create([
            'vatly_id' => $event->subscriptionId,
            'customer_id' => $event->customerId,
            'type' => $event->type,
            'plan_id' => $event->planId,
            'name' => $event->name,
            'quantity' => $event->quantity,
        ]);
    }
}
