<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Customer;
use Vatly\Fluent\Actions\Responses\CreateCustomerResponse;

class CreateCustomer extends BaseAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function execute(array $payload, array $filters = []): CreateCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->create(
            payload: $payload,
            filters: $filters,
        );

        assert($apiResponse instanceof Customer);

        return CreateCustomerResponse::fromApiResponse($apiResponse);
    }
}
