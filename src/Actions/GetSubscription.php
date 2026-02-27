<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Subscription;
use Vatly\Fluent\Actions\Responses\GetSubscriptionResponse;

class GetSubscription extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $subscriptionId, array $parameters = []): GetSubscriptionResponse
    {
        $apiResponse = $this->vatlyApiClient->subscriptions->get($subscriptionId, $parameters);

        assert($apiResponse instanceof Subscription);

        return GetSubscriptionResponse::fromApiResponse($apiResponse);
    }
}
