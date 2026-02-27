<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions\Responses;

use Vatly\API\Resources\Customer;

/**
 * Response from getting a customer.
 */
final class GetCustomerResponse extends CustomerResponse
{
    public static function fromApiResponse(Customer $response): self
    {
        return new self(
            customerId: $response->id,
        );
    }
}
