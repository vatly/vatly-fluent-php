<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Customer;

class UpdateCustomer extends BaseAction
{
    /**
     * Update a customer's identity fields (`name`, `email`). Both are optional —
     * send whichever you want to change. Billing-address details (company name,
     * tax id, street, …) are not editable here; amend those through the hosted
     * billing-update flow instead.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filters
     */
    public function execute(string $customerId, array $data, array $filters = []): Customer
    {
        $customer = $this->vatlyApiClient->customers->update($customerId, $data, $filters);

        assert($customer instanceof Customer);

        return $customer;
    }
}
