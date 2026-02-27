<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions\Responses;

use Vatly\API\Resources\Customer;

/**
 * Base response for customer operations.
 */
abstract class CustomerResponse
{
    public function __construct(
        public readonly string $customerId,
    ) {
        //
    }

    abstract public static function fromApiResponse(Customer $response): static;
}
