<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\Responses\CreateCheckoutResponse;

class CreateCheckout extends BaseAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function execute(array $payload, array $filters = []): CreateCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->create(
            payload: $payload,
            filters: $filters,
        );

        assert($apiResponse instanceof Checkout);

        return CreateCheckoutResponse::fromApiResponse($apiResponse);
    }
}
