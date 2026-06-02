<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Chargeback;

class GetChargeback extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $chargebackId, array $parameters = []): Chargeback
    {
        $chargeback = $this->vatlyApiClient->chargebacks->get($chargebackId, $parameters);

        assert($chargeback instanceof Chargeback);

        return $chargeback;
    }
}
