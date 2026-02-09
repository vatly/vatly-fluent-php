<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\Actions\Responses\CreateCheckoutResponse;

class CreateCheckout extends BaseAction
{
    public function execute(array $payload, array $filters = []): CreateCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->create(
            payload: $payload,
            filters: $filters,
        );

        return CreateCheckoutResponse::fromApiResponse($apiResponse);
    }
}
