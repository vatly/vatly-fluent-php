<?php

namespace Vatly\API\Types;

class OrderStatus
{
    /**
     * The order is awaiting payment.
     */
    public const STATUS_PENDING = "pending";

    /**
     * The order has been paid.
     */
    public const STATUS_PAID = "paid";

    /**
     * The order payment failed.
     */
    public const STATUS_FAILED = "failed";
}
