<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Subscription;

class GetSubscription extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $subscriptionId, array $parameters = []): Subscription
    {
        $subscription = $this->vatlyApiClient->subscriptions->get($subscriptionId, $parameters);

        assert($subscription instanceof Subscription);

        return $subscription;
    }
}
