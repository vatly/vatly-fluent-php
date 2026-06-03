<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use Vatly\API\Resources\OrderLine as ApiOrderLine;
use Vatly\Fluent\Types\Money;
use Vatly\Fluent\Types\TaxSummary;

/**
 * Data for a single order line from Vatly.
 *
 * Carries the per-line breakdown so drivers can persist first-class order
 * lines and traverse subscription↔orders via the generic
 * (`productType`, `productId`) pair. A line links to a subscription when
 * `productType === 'subscription'` → `productId` is the `subscription_…` id.
 *
 * `productType` is carried as the raw API string (no fluent enum) so new
 * backend product types flow through without a fluent release.
 *
 * @immutable
 */
class OrderLineData
{
    public function __construct(
        public string $vatlyId,
        public string $description,
        public int $quantity,
        public int $basePrice,
        public int $total,
        public int $subtotal,
        public ?TaxSummary $taxSummary = null,
        public ?string $productType = null,
        public ?string $productId = null,
    ) {}

    public static function fromApiOrderLine(ApiOrderLine $line): self
    {
        return new self(
            vatlyId: $line->id,
            description: $line->description,
            quantity: $line->quantity,
            basePrice: Money::fromApiMoneyToCents($line->basePrice),
            total: Money::fromApiMoneyToCents($line->total),
            subtotal: Money::fromApiMoneyToCents($line->subtotal),
            taxSummary: TaxSummary::fromApiResource($line->taxes),
            productType: $line->productType,
            productId: $line->productId,
        );
    }
}
