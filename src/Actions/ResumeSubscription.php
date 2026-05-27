<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Subscription;

class ResumeSubscription extends BaseAction
{
    public function execute(string $subscriptionId): Subscription
    {
        /** @var Subscription $resource */
        $resource = $this->vatlyApiClient->subscriptions->resume($subscriptionId);

        return $resource;
    }
}
