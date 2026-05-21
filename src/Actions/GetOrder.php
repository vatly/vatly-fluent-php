<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Order;

class GetOrder extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $orderId, array $parameters = []): Order
    {
        $order = $this->vatlyApiClient->orders->get($orderId, $parameters);

        assert($order instanceof Order);

        return $order;
    }
}
