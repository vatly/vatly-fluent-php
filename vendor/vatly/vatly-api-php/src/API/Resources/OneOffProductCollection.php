<?php

namespace Vatly\API\Resources;

class OneOffProductCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'one_off_products';
    }

    protected function createResourceObject(): OneOffProduct
    {
        return new OneOffProduct($this->apiClient);
    }
}
