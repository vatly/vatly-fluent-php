<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Types\TaxSummaryCollection;

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
        public ?TaxSummaryCollection $taxSummary = null,
        public ?string $reason = null,
    ) {}
}
