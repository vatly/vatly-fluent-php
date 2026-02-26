<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\Fluent\Actions\Responses\GetSubscriptionResponse;

class GetSubscription extends BaseAction
{
    public function execute(string $subscriptionId, array $parameters = []): GetSubscriptionResponse
    {
        $apiResponse = $this->vatlyApiClient->subscriptions->get($subscriptionId, $parameters);

        return GetSubscriptionResponse::fromApiResponse($apiResponse);
    }
}
