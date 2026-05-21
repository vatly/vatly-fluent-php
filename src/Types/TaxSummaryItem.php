<?php

declare(strict_types=1);

namespace Vatly\Fluent\Types;

use Vatly\API\Types\TaxSummaryItem as ApiTaxSummaryItem;

/**
 * A single tax-rate bucket on an order: rate metadata + the amount it produced.
 *
 * Amount is in minor units (cents) for parity with the rest of the fluent API.
 *
 * @immutable
 */
class TaxSummaryItem
{
    public function __construct(
        public TaxSummaryRate $rate,
        public int $amount,
        public string $currency,
    ) {
    }

    public static function fromApiResource(ApiTaxSummaryItem $item): self
    {
        return new self(
            rate: TaxSummaryRate::fromApiResource($item->taxRate),
            amount: Money::fromApiMoneyToCents($item->amount),
            currency: $item->amount->currency,
        );
    }
}
