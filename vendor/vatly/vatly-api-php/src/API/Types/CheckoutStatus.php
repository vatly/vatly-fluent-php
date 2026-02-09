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
     * The checkout is completed.
     */
    public const STATUS_COMPLETED = "completed";

    /**
     * The checkout is expired.
     */
    public const STATUS_EXPIRED = "expired";

    /**
     * The checkout is pending.
     */
    public const STATUS_PENDING = "pending";
}
