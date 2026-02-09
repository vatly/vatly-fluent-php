<?php

namespace Vatly\API\Resources;

class SubscriptionCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'subscriptions';
    }

    protected function createResourceObject(): Subscription
    {
        return new Subscription($this->apiClient);
    }
}
