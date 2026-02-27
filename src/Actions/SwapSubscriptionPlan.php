<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Subscription;

class SwapSubscriptionPlan extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(
        string $subscriptionId,
        string $newPlanId,
        array $parameters = [],
    ): Subscription {
        $subscription = $this->vatlyApiClient->subscriptions->update($subscriptionId, array_merge(
            ['subscriptionPlanId' => $newPlanId],
            $parameters,
        ));

        assert($subscription instanceof Subscription);

        return $subscription;
    }
}
