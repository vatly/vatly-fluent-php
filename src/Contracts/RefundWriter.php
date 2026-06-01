<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use Vatly\Fluent\Data\StoreRefundData;
use Vatly\Fluent\Data\UpdateRefundData;

/**
 * Write-side of the refund repository contract.
 *
 * Pairs with {@see RefundReader}.
 */
interface RefundWriter
{
    /**
     * Store a new refund from Vatly.
     *
     * Returns `null` when the driver legitimately cannot route the store
     * (e.g. it can't locate the original order locally). Built-in reactions
     * tolerate null.
     */
    public function store(StoreRefundData $data): ?RefundInterface;

    /**
     * Update an existing refund from Vatly.
     */
    public function update(RefundInterface $refund, UpdateRefundData $data): RefundInterface;
}
