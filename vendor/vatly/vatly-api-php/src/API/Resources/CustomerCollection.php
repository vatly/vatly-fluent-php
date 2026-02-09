<?php

namespace Vatly\API\Resources;

class CustomerCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'customers';
    }

    protected function createResourceObject(): Customer
    {
        return new Customer($this->apiClient);
    }
}
