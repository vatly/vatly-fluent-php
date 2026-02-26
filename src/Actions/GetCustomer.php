<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\Fluent\Actions\Responses\GetCustomerResponse;

class GetCustomer extends BaseAction
{
    public function execute(string $customerId, array $parameters = []): GetCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->get($customerId, $parameters);

        return GetCustomerResponse::fromApiResponse($apiResponse);
    }
}
