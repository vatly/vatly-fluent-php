<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;

/**
 * Write-side of the order repository contract.
 *
 * Typehint this from webhook reactions that only persist orders —
 * order-paid, order-refunded. Pairs with {@see OrderReader}.
 */
interface OrderWriter
{
    /**
     * Store a new order from Vatly.
     *
     * Returns `null` when the driver legitimately cannot route the store
     * (e.g. the metadata doesn't match any host record the driver knows
     * how to persist against). Built-in reactions tolerate null.
     */
    public function store(StoreOrderData $data): ?OrderInterface;

    /**
     * Update an existing order from Vatly.
     *
     * When a cancellation webhook cancels the order, `$data` may carry a raw
     * {@see UpdateOrderData::$cancellationReason} ("payment_failure") alongside
     * `status`; persist it if your host tracks why an order was canceled. As
     * with `status`, `null` means "no change", so don't overwrite a stored
     * reason with null.
     */
    public function update(OrderInterface $order, UpdateOrderData $data): OrderInterface;
}
