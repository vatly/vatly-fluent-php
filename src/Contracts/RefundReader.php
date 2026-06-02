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
     * Drives {@see \Vatly\Fluent\OrderHandle::refunds()}.
     *
     * @return RefundInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array;
}
