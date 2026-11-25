<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\TaxSummaryCollection;

/**
 * Data for storing a new refund from Vatly.
 *
 * @immutable
 */
class StoreRefundData
{
    public function __construct(
        public string $vatlyId,
        public string $customerId,
        public string $status,
        public int $total,
        public string $currency,
        public string $originalOrderId,
        public bool $testmode,
        public ?int $subtotal = null,
        public ?TaxSummaryCollection $taxSummary = null,
        public ?string $hostCustomerId = null,
        /**
         * The `metadata` sent when the refund was created, or null when none was
         * sent (including refunds created before Vatly stored refund metadata).
         *
         * @var array<string, mixed>|null
         */
        public ?array $metadata = null,
    ) {}
}
