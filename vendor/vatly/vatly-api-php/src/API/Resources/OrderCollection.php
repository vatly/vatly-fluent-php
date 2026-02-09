<?php

namespace Vatly\API\Resources;

class OrderCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'orders';
    }

    protected function createResourceObject(): Order
    {
        return new Order($this->apiClient);
    }
}
