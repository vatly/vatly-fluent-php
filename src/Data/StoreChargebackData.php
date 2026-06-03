<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\TaxSummaryCollection;

/**
 * Data for storing a new chargeback from Vatly.
 *
 * @immutable
 */
class StoreChargebackData
{
    public function __construct(
        public string $vatlyId,
        public string $customerId,
        public string $status,
        public int $total,
        public string $currency,
        public string $originalOrderId,
        public ?string $reason = null,
        public ?int $subtotal = null,
        public ?TaxSummaryCollection $taxSummary = null,
        public ?string $hostCustomerId = null,
    ) {}
}
