<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\Actions\Responses\GetCustomerResponse;

class GetCustomer extends BaseAction
{
    public function execute(string $customerId, array $parameters = []): GetCustomerResponse
    {
        $apiResponse = $this->vatlyApiClient->customers->get($customerId, $parameters);

        return GetCustomerResponse::fromApiResponse($apiResponse);
    }
}
