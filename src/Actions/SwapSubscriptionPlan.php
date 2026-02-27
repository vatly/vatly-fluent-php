<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Subscription;
use Vatly\Fluent\Actions\Responses\SwapSubscriptionPlanResponse;

class SwapSubscriptionPlan extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(
        string $subscriptionId,
        string $newPlanId,
        array $parameters = [],
    ): SwapSubscriptionPlanResponse {
        $apiResponse = $this->vatlyApiClient->subscriptions->update($subscriptionId, array_merge(
            ['subscriptionPlanId' => $newPlanId],
            $parameters,
        ));

        assert($apiResponse instanceof Subscription);

        return SwapSubscriptionPlanResponse::fromApiResponse($apiResponse);
    }
}
