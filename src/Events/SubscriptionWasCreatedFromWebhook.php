<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\Fluent\Contracts\SubscriptionInterface;

/**
 * Event dispatched when a new local subscription record is created from a
 * `subscription.started` webhook.
 *
 * This is a driver-side domain event (vs the raw webhook event DTOs from
 * Vatly). It carries the freshly persisted local `SubscriptionInterface` and
 * fires exactly once per brand-new subscription row.
 *
 * @immutable
 */
class SubscriptionWasCreatedFromWebhook
{
    public function __construct(
        public SubscriptionInterface $subscription,
    ) {
        //
    }
}
