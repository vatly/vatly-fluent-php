<?php

namespace Vatly\API\Resources;

class RefundCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'refunds';
    }

    protected function createResourceObject(): Refund
    {
        return new Refund($this->apiClient);
    }
}
