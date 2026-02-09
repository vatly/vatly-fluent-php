<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

use Vatly\API\Resources\Customer;

/**
 * Base response for customer operations.
 */
class CustomerResponse
{
    public function __construct(
        public readonly string $customerId,
    ) {
        //
    }

    public static function fromApiResponse(Customer $response): static
    {
        return new static(
            customerId: $response->id,
        );
    }
}
