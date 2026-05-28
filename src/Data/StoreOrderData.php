<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\Fluent\Types\TaxSummary;

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
        public ?TaxSummary $taxSummary = null,
        public ?string $hostCustomerId = null,
    ) {}
}
