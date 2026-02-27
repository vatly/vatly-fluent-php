<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Checkout;

class GetCheckout extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $checkoutId, array $parameters = []): Checkout
    {
        $checkout = $this->vatlyApiClient->checkouts->get($checkoutId, $parameters);

        assert($checkout instanceof Checkout);

        return $checkout;
    }
}
