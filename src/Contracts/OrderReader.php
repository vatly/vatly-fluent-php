<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Read-side of the order repository contract.
 *
 * Typehint this for read-only consumers — order history views, invoice
 * URL lookups, etc. Pairs with {@see OrderWriter}.
 */
interface OrderReader
{
    /**
     * Find an order by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?OrderInterface;
}
