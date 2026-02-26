<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\Actions\Responses\SwapSubscriptionPlanResponse;

class SwapSubscriptionPlan extends BaseAction
{
    public function execute(
        string $subscriptionId,
        string $newPlanId,
        array $parameters = [],
    ): SwapSubscriptionPlanResponse {
        $apiResponse = $this->vatlyApiClient->subscriptions->update($subscriptionId, array_merge(
            ['subscriptionPlanId' => $newPlanId],
            $parameters,
        ));

        return SwapSubscriptionPlanResponse::fromApiResponse($apiResponse);
    }
}
