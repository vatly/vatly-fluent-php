<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Refund;

class GetRefund extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $refundId, array $parameters = []): Refund
    {
        $refund = $this->vatlyApiClient->refunds->get($refundId, $parameters);

        assert($refund instanceof Refund);

        return $refund;
    }
}
