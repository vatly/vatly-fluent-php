<?php

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\ChargebackLinks;
use Vatly\API\Types\Money;

class Chargeback extends BaseResource
{
    /**
     * @example chargeback_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example chargeback
     */
    public string $resource;

    /**
     * @example merchant_f7f3cbf96f6c444abd76aafaf99ecde9
     */
    public string $merchantId;

    /**
     * @example 2020-01-01
     */
    public string $createdAt;

    public bool $testmode;

    public Money $amount;

    public Money $settlementAmount;

    public string $reason;

    public ChargebackLinks $links;

    /**
     * The associated order ID
     * @example order_66fc8a40718b46bea50f1a25f456d243
     */
    public ?string $orderId = null;

    /**
     * The associated original order ID
     * @example order_66fc8a40718b46bea50f1a25f456d242
     */
    public string $originalOrderId;
}
