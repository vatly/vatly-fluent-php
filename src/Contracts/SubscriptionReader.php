<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Read-side of the subscription repository contract.
 *
 * Typehint this for read-only consumers — admin dashboards, status
 * predicates inside reactions, etc. Pairs with {@see SubscriptionWriter}.
 */
interface SubscriptionReader
{
    /**
     * Find a subscription by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?SubscriptionInterface;
}
