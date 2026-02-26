<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

class CancelSubscription extends BaseAction
{
    public function execute(string $subscriptionId): void
    {
        $this->vatlyApiClient->subscriptions->cancel($subscriptionId);
    }
}
