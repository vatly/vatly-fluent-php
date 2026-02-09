<?php

namespace Vatly\API\Resources;

class ChargebackCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'chargebacks';
    }

    protected function createResourceObject(): Chargeback
    {
        return new Chargeback($this->apiClient);
    }
}
