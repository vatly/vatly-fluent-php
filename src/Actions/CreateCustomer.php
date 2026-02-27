<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Customer;

class CreateCustomer extends BaseAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function execute(array $payload, array $filters = []): Customer
    {
        $customer = $this->vatlyApiClient->customers->create(
            payload: $payload,
            filters: $filters,
        );

        assert($customer instanceof Customer);

        return $customer;
    }
}
