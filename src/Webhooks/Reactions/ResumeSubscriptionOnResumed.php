<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\SubscriptionResumed;

/**
 * Clears the stored end date when a subscription is resumed at Vatly,
 * re-activating the local record.
 *
 * The inverse of {@see CancelSubscriptionOnCanceled}: that stamps `endsAt`,
 * this clears it via `UpdateSubscriptionData::clearEndsAt`. With `endsAt`
 * null the {@see \Vatly\Fluent\Concerns\DerivesSubscriptionState} predicates
 * report the subscription as active/recurring again. Matters most for resumes
 * that happen out-of-band (hosted portal, dashboard) rather than through the
 * SDK's own `SubscriptionHandle::resume()`, where the local record would
 * otherwise keep showing the prior cancellation's end date.
 *
 * Find-or-skip: a resume for an untracked subscription is a no-op.
 *
 * @immutable
 */
class ResumeSubscriptionOnResumed implements WebhookReactionInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof SubscriptionResumed;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof SubscriptionResumed) {
            return;
        }

        $subscription = $this->subscriptions->findByVatlyId($event->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $this->subscriptions->update($subscription, new UpdateSubscriptionData(
            clearEndsAt: true,
        ));
    }
}
