<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\OneOffProductLinks;
use Vatly\API\Types\Money;

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

    public OneOffProductLinks $links;
}
