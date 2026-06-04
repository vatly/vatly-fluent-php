<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\Mandate;

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
        public bool $testmode,
        public int $quantity = 1,
        public ?string $hostCustomerId = null,
        /**
         * Payment method on file at store-time. Null when the mandate
         * isn't known yet (typical for freshly-subscribed customers — the
         * API briefly returns `mandate: null` before payment binds; later
         * `SubscriptionHandle::sync()` or a `subscription.billing_updated`
         * event populates it).
         */
        public ?Mandate $mandate = null,
    ) {}
}
