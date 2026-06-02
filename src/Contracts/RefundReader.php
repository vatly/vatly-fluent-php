<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Read-side of the refund repository contract.
 *
 * Pairs with {@see RefundWriter}.
 */
interface RefundReader
{
    /**
     * Find a refund by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?RefundInterface;

    /**
     * List every refund tracked locally for a Vatly customer.
     *
     * @return RefundInterface[]
     */
    public function listForCustomer(string $customerId): array;

    /**
     * List every refund tracked locally against a given original order.
     *
     * Drives {@see \Vatly\Fluent\OrderHandle::refunds()} and the cumulative
     * read in {@see \Vatly\Fluent\Webhooks\Reactions\SyncOrderOnRefundChange}.
     *
     * @return RefundInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array;

    /**
     * Sum the subtotals (net of tax, in integer cents) of the *completed*
     * refunds recorded against an original order.
     *
     * "Completed" means funds returned — i.e. refunds for which
     * {@see RefundInterface::isCompleted()} is true. Failed and canceled
     * refunds must not be counted, otherwise a canceled refund would wrongly
     * hold the order in a refunded state.
     *
     * Used by {@see \Vatly\Fluent\Webhooks\Reactions\SyncOrderOnRefundChange}
     * to decide whether an order is fully or partially refunded. Because two
     * refunds can complete on the same order concurrently, driver
     * implementations should aggregate inside a transaction that locks the
     * order row (e.g. `SELECT … FOR UPDATE`) before summing and writing, so
     * the derived status doesn't race.
     */
    public function sumSubtotalsForOrder(string $vatlyOrderId): int;
}
