<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\Actions\Responses\CreateCustomerResponse;

class CreateCustomer extends BaseAction
{
    public function execute(array $payload, array $filters = []): CreateCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->create(
            payload: $payload,
            filters: $filters,
        );

        return CreateCustomerResponse::fromApiResponse($apiResponse);
    }
}
