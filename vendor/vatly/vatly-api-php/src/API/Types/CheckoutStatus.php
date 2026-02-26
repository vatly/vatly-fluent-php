<?php

namespace Vatly\API\Types;

class CheckoutStatus
{
    /**
     * The checkout has just been created.
     */
    public const STATUS_CREATED = "created";

    /**
     * The checkout has been paid.
     */
    public const STATUS_PAID = "paid";

    /**
     * The checkout has been canceled.
     */
    public const STATUS_CANCELED = "canceled";

    /**
     * The checkout payment failed.
     */
    public const STATUS_FAILED = "failed";

    /**
     * The checkout is expired.
     */
    public const STATUS_EXPIRED = "expired";
}
