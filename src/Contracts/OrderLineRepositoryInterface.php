<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Full order-line repository — both read and write sides.
 *
 * Supplying this to {@see \Vatly\Fluent\Wiring} is optional: when present the
 * built-in {@see \Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid} reaction
 * persists each line carried on `order.paid`; when absent orders are still
 * persisted (sans lines) and {@see \Vatly\Fluent\OrderHandle::lines()} returns
 * an empty array — keeping existing drivers back-compatible.
 */
interface OrderLineRepositoryInterface extends OrderLineReader, OrderLineWriter
{
    //
}
