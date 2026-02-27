<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Checkout;

class CreateCheckout extends BaseAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     */
    public function execute(array $payload, array $filters = []): Checkout
    {
        $checkout = $this->vatlyApiClient->checkouts->create(
            payload: $payload,
            filters: $filters,
        );

        assert($checkout instanceof Checkout);

        return $checkout;
    }
}
