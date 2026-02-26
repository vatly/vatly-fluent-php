<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\OneOffProductLinks;
use Vatly\API\Types\Money;
use Vatly\API\Types\ProductStatus;

class OneOffProduct extends BaseResource
{
    /**
     * @example one_off_product_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example one_off_product
     */
    public string $resource;

    public string $name;

    public string $description;

    /**
     * Price before any taxes and/or discounts are applied.
     */
    public Money $basePrice;

    public bool $testmode;

    /** @see ProductStatus */
    public string $status;

    public string $createdAt;

    public OneOffProductLinks $links;

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
