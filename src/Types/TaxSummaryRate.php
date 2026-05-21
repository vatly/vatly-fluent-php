<?php

declare(strict_types=1);

namespace Vatly\Fluent\Types;

use Vatly\API\Types\TaxSummaryRate as ApiTaxSummaryRate;

/**
 * @immutable
 */
class TaxSummaryRate
{
    public function __construct(
        public string $name,
        public float $percentage,
        public float $taxablePercentage,
    ) {
    }

    public static function fromApiResource(ApiTaxSummaryRate $rate): self
    {
        return new self(
            name: $rate->name,
            percentage: $rate->percentage,
            taxablePercentage: $rate->taxablePercentage,
        );
    }
}
