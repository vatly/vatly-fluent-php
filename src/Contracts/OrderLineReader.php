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
     * List every line that belongs to a given subscription, including the
     * initial signup line, so consumers reach the orders a subscription
     * generated (initial + renewals) from local state.
     *
     * A line matches when either holds:
     *
     * - `getSubscriptionId() === $subscriptionId` — covers the initial signup
     *   line (whose `productType` is `subscription_plan`) and renewals;
     * - `getProductType() === 'subscription'` and
     *   `getProductId() === $subscriptionId` — covers renewal lines stored
     *   before Vatly carried the subscription link, which have no
     *   subscription id.
     *
     * Return each matching line once. Keeps the join/query concern in the
     * driver's repository (mirroring {@see RefundReader::listForOrder()});
     * drivers index `subscription_id` and `(product_type, product_id)` to
     * back this.
     *
     * @return OrderLineInterface[]
     */
    public function listForSubscription(string $subscriptionId): array;
}
