<?php

namespace Vatly\API\Resources;

class SubscriptionPlanCollection extends BaseResourcePage
{
    public function getCollectionResourceName(): ?string
    {
        return 'subscription_plans';
    }

    protected function createResourceObject(): SubscriptionPlan
    {
        return new SubscriptionPlan($this->apiClient);
    }
}
