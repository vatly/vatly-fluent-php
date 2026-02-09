<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\OrderLineLinks;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxesCollection;

class OrderLine extends BaseResource
{
    /**
     * @example order_item_2a46f4c01d3b47979f4d7b3f58c98be7
     */
    public string $id;

    /**
     * @example orderline
     */
    public string $resource;

    /**
     * @example order_66fc8a40718b46bea50f1a25f456d243
     */
    public string $orderId;

    /**
     * @example PDF Book
     */
    public string $description;

    public int $quantity;

    public Money $basePrice;

    public Money $total;


    public Money $subtotal;

    public TaxesCollection $taxes;

    public OrderLineLinks $links;
}
