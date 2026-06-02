<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use Vatly\Fluent\Data\StoreChargebackData;
use Vatly\Fluent\Data\UpdateChargebackData;

/**
 * Write-side of the chargeback repository contract.
 *
 * Pairs with {@see ChargebackReader}.
 */
interface ChargebackWriter
{
    /**
     * Store a new chargeback from Vatly.
     *
     * Returns `null` when the driver legitimately cannot route the store
     * (e.g. it can't locate the original order locally). Built-in reactions
     * tolerate null.
     */
    public function store(StoreChargebackData $data): ?ChargebackInterface;

    /**
     * Update an existing chargeback from Vatly (e.g. on reversal).
     */
    public function update(ChargebackInterface $chargeback, UpdateChargebackData $data): ChargebackInterface;
}
