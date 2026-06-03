<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\Fluent\Contracts\OrderInterface;

/**
 * Event dispatched when a new local order record is created from an
 * `order.paid` webhook.
 *
 * This is a driver-side domain event (vs the raw webhook event DTOs from
 * Vatly). It carries the freshly persisted local `OrderInterface` and fires
 * exactly once per brand-new order row.
 *
 * @immutable
 */
class OrderWasCreatedFromWebhook
{
    public function __construct(
        public OrderInterface $order,
    ) {
        //
    }
}
