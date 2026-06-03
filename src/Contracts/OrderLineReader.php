<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Read-side of the order-line repository contract.
 *
 * Pairs with {@see OrderLineWriter}.
 */
interface OrderLineReader
{
    /**
     * List every line tracked locally for a given order.
     *
     * Drives {@see \Vatly\Fluent\OrderHandle::lines()}.
     *
     * @return OrderLineInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array;

    /**
     * List every line that links to a given subscription — i.e. lines whose
     * `productType === 'subscription'` and `productId === $subscriptionId`.
     *
     * Keeps the join/query concern in the driver's repository (mirroring
     * {@see RefundReader::listForOrder()}); drivers index `(product_type,
     * product_id)` to back this. Lets consumers reach the orders a
     * subscription generated (initial + renewals) from local state.
     *
     * @return OrderLineInterface[]
     */
    public function listForSubscription(string $subscriptionId): array;
}
