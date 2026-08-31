<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\CustomerCollection;

class ListCustomersByEmail extends BaseAction
{
    /**
     * Look up customers by email address — the way back to a customer id you no
     * longer have. The address is canonicalized before matching, exactly as on
     * write. An address can be held by more than one customer, so this always
     * yields a (possibly empty) collection.
     *
     * @param array<string, mixed> $parameters
     */
    public function execute(string $email, array $parameters = []): CustomerCollection
    {
        $collection = $this->vatlyApiClient->customers->listByEmail($email, $parameters);

        assert($collection instanceof CustomerCollection);

        return $collection;
    }
}
