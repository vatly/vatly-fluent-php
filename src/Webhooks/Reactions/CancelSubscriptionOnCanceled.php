<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use DateTimeImmutable;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;

class CancelSubscriptionOnCanceled implements WebhookReactionInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
    ) {
        //
    }

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionCanceledImmediately
            || $event instanceof SubscriptionCanceledWithGracePeriod;
    }

    public function handle(object $event): void
    {
        $subscriptionId = match (true) {
            $event instanceof SubscriptionCanceledImmediately => $event->subscriptionId,
            $event instanceof SubscriptionCanceledWithGracePeriod => $event->subscriptionId,
            default => throw new \InvalidArgumentException('Unsupported event type'),
        };

        $subscription = $this->subscriptions->findByVatlyId($subscriptionId);

        if ($subscription === null) {
            return;
        }

        $endsAt = match (true) {
            $event instanceof SubscriptionCanceledImmediately => new DateTimeImmutable(),
            $event instanceof SubscriptionCanceledWithGracePeriod => $event->endsAt,
            default => throw new \InvalidArgumentException('Unsupported event type'),
        };

        $this->subscriptions->update($subscription, [
            'ends_at' => $endsAt,
        ]);
    }
}
