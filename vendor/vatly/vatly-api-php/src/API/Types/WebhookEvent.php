<?php

namespace Vatly\API\Types;

class WebhookEvent
{
    public const CHARGEBACK_RECEIVED = 'chargeback.received';
    public const CHARGEBACK_REVERSED = 'chargeback.reversed';
    public const ORDER_CANCELED = 'order.canceled';
    public const ORDER_PAID = 'order.paid';
    public const REFUND_COMPLETED = 'refund.completed';
    public const REFUND_FAILED = 'refund.failed';
    public const REFUND_CANCELED = 'refund.canceled';
    public const SUBSCRIPTION_STARTED = 'subscription.started';
}
