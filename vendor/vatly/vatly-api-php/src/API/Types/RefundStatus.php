<?php

namespace Vatly\API\Types;

class RefundStatus
{
    /**
     * The refund is being processed.
     */
    public const PENDING = "pending";

    /**
     * The refund completed successfully.
     */
    public const COMPLETED = "completed";

    /**
     * The refund has failed after processing.
     */
    public const FAILED = "failed";

    /**
     * The refund was canceled and will no longer be processed.
     */
    public const CANCELED = "canceled";
}
