<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\SubscriptionPlanLinks;
use Vatly\API\Types\Money;

class SubscriptionPlan extends BaseResource
{
    /**
     * @example subscription_plan_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example subscription_plan
     */
    public string $resource;

    public string $name;

    public string $description;

    public string $interval;

    public int $intervalCount;

    /**
     * Price before any taxes and/or discounts are applied.
     */
    public Money $basePrice;

    public SubscriptionPlanLinks $links;
}
