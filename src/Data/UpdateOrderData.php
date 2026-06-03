<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\TaxSummaryCollection;

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
        public ?TaxSummaryCollection $taxSummary = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {}
}
