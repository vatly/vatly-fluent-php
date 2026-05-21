<?php

declare(strict_types=1);

namespace Vatly\Fluent\Types;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Vatly\API\Types\TaxSummaryCollection as ApiTaxSummaryCollection;

/**
 * Per-rate tax breakdown for an order. Iterable and countable.
 *
 * @immutable
 * @implements IteratorAggregate<int, TaxSummaryItem>
 */
class TaxSummary implements IteratorAggregate, Countable
{
    /**
     * @param TaxSummaryItem[] $items
     */
    public function __construct(
        public array $items = [],
    ) {
    }

    public static function fromApiResource(ApiTaxSummaryCollection $collection): self
    {
        return new self(
            items: array_map(
                fn ($item) => TaxSummaryItem::fromApiResource($item),
                $collection->items,
            ),
        );
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<int, array{rate: array{name: string, percentage: float, taxablePercentage: float}, amount: int, currency: string}>
     */
    public function toArray(): array
    {
        return array_map(
            fn (TaxSummaryItem $item) => [
                'rate' => [
                    'name' => $item->rate->name,
                    'percentage' => $item->rate->percentage,
                    'taxablePercentage' => $item->rate->taxablePercentage,
                ],
                'amount' => $item->amount,
                'currency' => $item->currency,
            ],
            $this->items,
        );
    }
}
