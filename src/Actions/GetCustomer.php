<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Customer;
use Vatly\Fluent\Actions\Responses\GetCustomerResponse;

class GetCustomer extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $customerId, array $parameters = []): GetCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->get($customerId, $parameters);

        assert($apiResponse instanceof Customer);

        return GetCustomerResponse::fromApiResponse($apiResponse);
    }
}
