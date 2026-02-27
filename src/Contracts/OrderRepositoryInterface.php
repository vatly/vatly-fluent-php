<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for order persistence.
 */
interface OrderRepositoryInterface
{
    /**
     * Find an order by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?OrderInterface;

    /**
     * Find all orders for an owner.
     *
     * @return OrderInterface[]
     */
    public function findAllByOwner(BillableInterface $owner): array;

    /**
     * Create a new order.
     */
    public function create(array $attributes): OrderInterface;

    /**
     * Update an order.
     */
    public function update(OrderInterface $order, array $attributes): OrderInterface;
}
