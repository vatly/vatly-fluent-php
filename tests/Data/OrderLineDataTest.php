<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Data;

use Mockery;
use Vatly\API\Resources\OrderLine as ApiOrderLine;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Data\OrderLineData;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\TaxSummary;

class OrderLineDataTest extends TestCase
{
    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $taxSummary = TaxSummary::empty();

        $data = new OrderLineData(
            vatlyId: 'order_item_1',
            description: 'PDF Book',
            quantity: 2,
            basePrice: 1000,
            total: 2420,
            subtotal: 2000,
            taxSummary: $taxSummary,
            productType: 'one_off_product',
            productId: 'product_book',
        );

        $this->assertSame('order_item_1', $data->vatlyId);
        $this->assertSame('PDF Book', $data->description);
        $this->assertSame(2, $data->quantity);
        $this->assertSame(1000, $data->basePrice);
        $this->assertSame(2420, $data->total);
        $this->assertSame(2000, $data->subtotal);
        $this->assertSame($taxSummary, $data->taxSummary);
        $this->assertSame('one_off_product', $data->productType);
        $this->assertSame('product_book', $data->productId);
    }

    public function test_product_fields_and_tax_summary_default_to_null(): void
    {
        $data = new OrderLineData(
            vatlyId: 'order_item_1',
            description: 'Line',
            quantity: 1,
            basePrice: 1000,
            total: 1000,
            subtotal: 1000,
        );

        $this->assertNull($data->taxSummary);
        $this->assertNull($data->productType);
        $this->assertNull($data->productId);
    }

    public function test_it_builds_from_an_api_order_line_converting_money_to_cents(): void
    {
        $line = new ApiOrderLine(Mockery::mock(VatlyApiClient::class));
        $line->id = 'order_item_sub';
        $line->resource = 'orderline';
        $line->description = 'Pro plan';
        $line->quantity = 1;
        $line->productType = 'subscription';
        $line->productId = 'subscription_abc';
        $line->basePrice = new Money('EUR', '20.00');
        $line->total = new Money('EUR', '24.20');
        $line->subtotal = new Money('EUR', '20.00');
        $line->taxes = new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '4.20'],
            ],
        ]);

        $data = OrderLineData::fromApiOrderLine($line);

        $this->assertSame('order_item_sub', $data->vatlyId);
        $this->assertSame('Pro plan', $data->description);
        $this->assertSame(1, $data->quantity);
        $this->assertSame(2000, $data->basePrice);
        $this->assertSame(2420, $data->total);
        $this->assertSame(2000, $data->subtotal);
        $this->assertSame('subscription', $data->productType);
        $this->assertSame('subscription_abc', $data->productId);
        $this->assertNotNull($data->taxSummary);
        $this->assertCount(1, $data->taxSummary);
        $this->assertSame(420, $data->taxSummary->items[0]->amount);
        $this->assertSame('VAT', $data->taxSummary->items[0]->rate->name);
    }

    public function test_it_carries_null_product_fields_when_the_api_line_is_unattributed(): void
    {
        $line = new ApiOrderLine(Mockery::mock(VatlyApiClient::class));
        $line->id = 'order_item_legacy';
        $line->resource = 'orderline';
        $line->description = 'Legacy line';
        $line->quantity = 1;
        $line->productType = null;
        $line->productId = null;
        $line->basePrice = new Money('EUR', '10.00');
        $line->total = new Money('EUR', '10.00');
        $line->subtotal = new Money('EUR', '10.00');
        $line->taxes = new TaxSummaryCollection([]);

        $data = OrderLineData::fromApiOrderLine($line);

        $this->assertNull($data->productType);
        $this->assertNull($data->productId);
        $this->assertNotNull($data->taxSummary);
        $this->assertCount(0, $data->taxSummary);
    }
}
