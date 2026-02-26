<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\SubscriptionPlanLinks;
use Vatly\API\Types\Money;
use Vatly\API\Types\ProductStatus;

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

    public bool $testmode;

    /** @see ProductStatus */
    public string $status;

    public string $createdAt;

    public SubscriptionPlanLinks $links;

    public function isApproved(): bool
    {
        return $this->status === ProductStatus::APPROVED;
    }

    public function isDraft(): bool
    {
        return $this->status === ProductStatus::DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === ProductStatus::ARCHIVED;
    }
}
