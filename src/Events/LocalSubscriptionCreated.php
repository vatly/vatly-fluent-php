<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\Fluent\Contracts\SubscriptionInterface;

class LocalSubscriptionCreated
{
    public SubscriptionInterface $subscription;

    public function __construct(SubscriptionInterface $subscription)
    {
        $this->subscription = $subscription;
    }
}
