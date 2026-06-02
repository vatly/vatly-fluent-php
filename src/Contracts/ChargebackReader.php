<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Read-side of the chargeback repository contract.
 *
 * Pairs with {@see ChargebackWriter}.
 */
interface ChargebackReader
{
    /**
     * Find a chargeback by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?ChargebackInterface;

    /**
     * List every chargeback tracked locally for a Vatly customer.
     *
     * @return ChargebackInterface[]
     */
    public function listForCustomer(string $customerId): array;

    /**
     * List every chargeback tracked locally against a given original order.
     *
     * Drives {@see \Vatly\Fluent\OrderHandle::chargebacks()}.
     *
     * @return ChargebackInterface[]
     */
    public function listForOrder(string $vatlyOrderId): array;
}
