<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Customer;

class GetCustomer extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $customerId, array $parameters = []): Customer
    {
        $customer = $this->vatlyApiClient->customers->get($customerId, $parameters);

        assert($customer instanceof Customer);

        return $customer;
    }
}
