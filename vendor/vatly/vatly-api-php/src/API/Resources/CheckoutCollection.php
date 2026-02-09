<?php

namespace Vatly\API\Resources;

class CheckoutCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'checkouts';
    }

    protected function createResourceObject(): Checkout
    {
        return new Checkout($this->apiClient);
    }
}
