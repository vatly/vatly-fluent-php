<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use Vatly\API\Types\OrderLineData;

/**
 * Write-side of the order-line repository contract.
 *
 * Pairs with {@see OrderLineReader}.
 *
 * Order lines are immutable once an order is paid, so — unlike
 * {@see RefundWriter} — there is no update path: the built-in
 * {@see \Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid} reaction only
 * stores lines when first persisting a new order.
 */
interface OrderLineWriter
{
    /**
     * Store a new order line from Vatly.
     *
     * Persist `$data->relatedResources?->subscriptionId` alongside the line so
     * {@see OrderLineInterface::getSubscriptionId()} and
     * {@see OrderLineReader::listForSubscription()} can find the initial
     * signup order, not only renewals.
     *
     * Returns `null` when the driver legitimately cannot route the store
     * (e.g. it can't locate the parent order locally). Built-in reactions
     * tolerate null.
     */
    public function store(OrderLineData $data, string $vatlyOrderId): ?OrderLineInterface;
}
