<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use DateTimeImmutable;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;

class CancelSubscriptionOnCanceled implements WebhookReactionInterface
{
    private SubscriptionRepositoryInterface $subscriptions;

    public function __construct(SubscriptionRepositoryInterface $subscriptions)
    {
        $this->subscriptions = $subscriptions;
    }

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionCanceledImmediately
            || $event instanceof SubscriptionCanceledWithGracePeriod;
    }

    public function handle(object $event): void
    {
        if ($event instanceof SubscriptionCanceledImmediately) {
            $subscriptionId = $event->subscriptionId;
        } elseif ($event instanceof SubscriptionCanceledWithGracePeriod) {
            $subscriptionId = $event->subscriptionId;
        } else {
            throw new \InvalidArgumentException('Unsupported event type');
        }

        $subscription = $this->subscriptions->findByVatlyId($subscriptionId);

        if ($subscription === null) {
            return;
        }

        if ($event instanceof SubscriptionCanceledImmediately) {
            $endsAt = new DateTimeImmutable();
        } elseif ($event instanceof SubscriptionCanceledWithGracePeriod) {
            $endsAt = $event->endsAt;
        } else {
            throw new \InvalidArgumentException('Unsupported event type');
        }

        $this->subscriptions->update($subscription, new UpdateSubscriptionData(
            endsAt: $endsAt,
        ));
    }
}
