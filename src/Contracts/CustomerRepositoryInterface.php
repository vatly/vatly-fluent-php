<?php

declare(strict_types=1);

namespace Vatly\Contracts;

/**
 * Interface for customer/billable persistence and lookup.
 */
interface CustomerRepositoryInterface
{
    /**
     * Find a billable by its Vatly customer ID.
     */
    public function findByVatlyId(string $vatlyId): ?BillableInterface;

    /**
     * Find a billable by its Vatly customer ID or fail.
     *
     * @throws \Vatly\Exceptions\InvalidCustomer
     */
    public function findByVatlyIdOrFail(string $vatlyId): BillableInterface;

    /**
     * Save a billable entity.
     */
    public function save(BillableInterface $billable): void;
}
