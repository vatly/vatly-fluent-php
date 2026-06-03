<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\OrderLineData;
use Vatly\API\Types\TaxSummaryCollection;

/**
 * Data for storing a new order from Vatly.
 *
 * @immutable
 */
class StoreOrderData
{
    public function __construct(
        public string $vatlyId,
        public string $customerId,
        public string $status,
        public int $total,
        public string $currency,
        public ?string $invoiceNumber = null,
        public ?string $paymentMethod = null,
        public ?int $subtotal = null,
        public ?TaxSummaryCollection $taxSummary = null,
        public ?string $hostCustomerId = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
        /**
         * The order's lines, persisted as first-class read entities. Defaults
         * to empty for back-compat: drivers/reactions that don't carry lines
         * keep working unchanged.
         *
         * @var OrderLineData[]
         */
        public array $lines = [],
    ) {}
}
