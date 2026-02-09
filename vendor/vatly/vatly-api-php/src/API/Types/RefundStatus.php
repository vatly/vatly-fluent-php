<?php

namespace Vatly\API\Types;

class RefundStatus
{
    /**
     * The refund has been paid.
     */
    public const REFUNDED = "refunded";


    /**
     * The refund was canceled and will no longer be processed.
     */
    public const CANCELED = "canceled";

    /**
     * The refund is ready to be sent to the bank. Can still be canceled.
     */
    public const PENDING = "pending";

    /**
     * The refund is queued due to a lack of balance.
     */
    public const QUEUED = "queued";

    /**
     * The refund has failed after processing. The funds will be returned to your account.
     */
    public const FAILED = "failed";

    /**
     * The refund is being processed. Cancellation is no longer possible.
     */
    public const PROCESSING = "processing";
}
