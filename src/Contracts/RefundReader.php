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
}
