<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

/**
 * Data for storing a new subscription from Vatly.
 *
 * @immutable
 */
class StoreSubscriptionData
{
    public function __construct(
        public string $vatlyId,
        public string $customerId,
        public string $type,
        public string $planId,
        public string $name,
        public int $quantity = 1,
        public ?string $hostCustomerId = null,
    ) {}
}
