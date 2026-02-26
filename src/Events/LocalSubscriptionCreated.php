<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\Fluent\Contracts\SubscriptionInterface;

/**
 * Event dispatched when a local subscription record is created.
 *
 * This is an application-level event (vs webhook events from Vatly).
 */
class LocalSubscriptionCreated
{
    public function __construct(
        public readonly SubscriptionInterface $subscription,
    ) {
        //
    }
}
