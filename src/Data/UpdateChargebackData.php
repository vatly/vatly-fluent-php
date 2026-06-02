<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\Fluent\Types\TaxSummary;

/**
 * Data for updating an existing chargeback from Vatly (e.g. on reversal).
 *
 * @immutable
 */
class UpdateChargebackData
{
    public function __construct(
        public ?string $status = null,
        public ?int $total = null,
        public ?string $currency = null,
        public ?int $subtotal = null,
        public ?TaxSummary $taxSummary = null,
        public ?string $reason = null,
    ) {}
}
