<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\Fluent\Types\TaxSummary;

/**
 * Data for updating an existing order from Vatly.
 *
 * @immutable
 */
class UpdateOrderData
{
    public function __construct(
        public ?string $status = null,
        public ?int $total = null,
        public ?string $currency = null,
        public ?string $invoiceNumber = null,
        public ?string $paymentMethod = null,
        public ?int $subtotal = null,
        public ?TaxSummary $taxSummary = null,
    ) {}
}
