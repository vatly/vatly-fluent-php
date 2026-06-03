<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Resources\OrderLine as ApiOrderLine;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Data\OrderLineData;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\TaxSummary;

class OrderPaidTest extends TestCase
{
    public function test_it_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.paid', OrderPaid::VATLY_EVENT_NAME);
    }

    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $event = new OrderPaid(
            customerId: 'cus_123',
            orderId: 'ord_456',
            status: 'paid',
            total: 9900,
            subtotal: 8182,
            taxSummary: TaxSummary::empty(),
            currency: 'EUR',
            invoiceNumber: 'INV-2024-001',
            paymentMethod: 'credit_card',
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('ord_456', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(9900, $event->total);
        $this->assertSame(8182, $event->subtotal);
        $this->assertCount(0, $event->taxSummary);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('INV-2024-001', $event->invoiceNumber);
        $this->assertSame('credit_card', $event->paymentMethod);
    }

    public function test_it_builds_from_api_order_resource_with_tax_breakdown(): void
    {
        $apiOrder = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $apiOrder->id = 'ord_123';
        $apiOrder->customerId = 'cus_456';
        $apiOrder->total = new Money('USD', '49.99');
        $apiOrder->subtotal = new Money('USD', '41.31');
        $apiOrder->invoiceNumber = 'INV-2024-002';
        $apiOrder->paymentMethod = 'ideal';
        $apiOrder->status = 'paid';
        $apiOrder->taxSummary = new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'USD', 'value' => '8.68'],
            ],
        ]);
        $apiOrder->lines = [];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(4999, $event->total);
        $this->assertSame(4131, $event->subtotal);
        $this->assertSame('USD', $event->currency);
        $this->assertSame('INV-2024-002', $event->invoiceNumber);
        $this->assertSame('ideal', $event->paymentMethod);
        $this->assertCount(1, $event->taxSummary);
        $this->assertSame(868, $event->taxSummary->items[0]->amount);
        $this->assertSame('VAT', $event->taxSummary->items[0]->rate->name);
    }

    public function test_it_builds_from_api_order_with_no_customer_or_invoice(): void
    {
        $apiOrder = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $apiOrder->id = 'ord_789';
        $apiOrder->customerId = null;
        $apiOrder->total = new Money('GBP', '15.00');
        $apiOrder->subtotal = new Money('GBP', '12.40');
        $apiOrder->invoiceNumber = null;
        $apiOrder->paymentMethod = null;
        $apiOrder->status = 'paid';
        $apiOrder->taxSummary = new TaxSummaryCollection([]);
        $apiOrder->lines = [];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertSame('', $event->customerId);
        $this->assertSame('ord_789', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(1500, $event->total);
        $this->assertSame(1240, $event->subtotal);
        $this->assertSame('GBP', $event->currency);
        $this->assertNull($event->invoiceNumber);
        $this->assertNull($event->paymentMethod);
        $this->assertCount(0, $event->taxSummary);
        $this->assertNull($event->metadata);
    }

    public function test_it_carries_metadata_from_api_order_array(): void
    {
        $apiOrder = $this->makeApiOrder();
        $apiOrder->metadata = ['fluentcart_transaction_id' => 'tx_42', 'source' => 'checkout'];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertSame(
            ['fluentcart_transaction_id' => 'tx_42', 'source' => 'checkout'],
            $event->metadata,
        );
    }

    public function test_it_normalizes_object_metadata_from_api_order(): void
    {
        $apiOrder = $this->makeApiOrder();
        $apiOrder->metadata = (object) ['fluentcart_transaction_id' => 'tx_42'];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertSame(['fluentcart_transaction_id' => 'tx_42'], $event->metadata);
    }

    public function test_it_maps_api_order_lines_with_product_fields_and_money_to_cents(): void
    {
        $apiOrder = $this->makeApiOrder();
        $apiOrder->lines = [
            $this->makeApiLine(
                id: 'order_item_sub',
                description: 'Pro plan — monthly',
                quantity: 1,
                basePrice: '20.00',
                total: '24.20',
                subtotal: '20.00',
                productType: 'subscription',
                productId: 'subscription_abc',
            ),
            $this->makeApiLine(
                id: 'order_item_addon',
                description: 'Seat add-on',
                quantity: 3,
                basePrice: '5.00',
                total: '18.15',
                subtotal: '15.00',
                productType: 'one_off_product',
                productId: 'product_seat',
            ),
        ];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertCount(2, $event->lines);
        $this->assertContainsOnlyInstancesOf(OrderLineData::class, $event->lines);

        $sub = $event->lines[0];
        $this->assertSame('order_item_sub', $sub->vatlyId);
        $this->assertSame('Pro plan — monthly', $sub->description);
        $this->assertSame(1, $sub->quantity);
        $this->assertSame(2000, $sub->basePrice);
        $this->assertSame(2420, $sub->total);
        $this->assertSame(2000, $sub->subtotal);
        $this->assertSame('subscription', $sub->productType);
        $this->assertSame('subscription_abc', $sub->productId);
        $this->assertCount(1, $sub->taxSummary);
        $this->assertSame('VAT', $sub->taxSummary->items[0]->rate->name);

        $addon = $event->lines[1];
        $this->assertSame('order_item_addon', $addon->vatlyId);
        $this->assertSame(3, $addon->quantity);
        $this->assertSame(500, $addon->basePrice);
        $this->assertSame(1815, $addon->total);
        $this->assertSame(1500, $addon->subtotal);
        $this->assertSame('one_off_product', $addon->productType);
        $this->assertSame('product_seat', $addon->productId);
    }

    public function test_it_maps_a_line_with_null_product_attribution(): void
    {
        $apiOrder = $this->makeApiOrder();
        $apiOrder->lines = [
            $this->makeApiLine(
                id: 'order_item_unattributed',
                description: 'Legacy line',
                quantity: 1,
                basePrice: '10.00',
                total: '10.00',
                subtotal: '10.00',
                productType: null,
                productId: null,
            ),
        ];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertCount(1, $event->lines);
        $this->assertNull($event->lines[0]->productType);
        $this->assertNull($event->lines[0]->productId);
    }

    public function test_it_carries_an_empty_lines_array_when_the_order_has_no_lines(): void
    {
        $apiOrder = $this->makeApiOrder();
        $apiOrder->lines = [];

        $event = OrderPaid::fromApiOrder($apiOrder);

        $this->assertSame([], $event->lines);
    }

    private function makeApiOrder(): ApiOrder
    {
        $apiOrder = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $apiOrder->id = 'ord_meta';
        $apiOrder->customerId = 'cus_meta';
        $apiOrder->total = new Money('EUR', '10.00');
        $apiOrder->subtotal = new Money('EUR', '8.26');
        $apiOrder->invoiceNumber = null;
        $apiOrder->paymentMethod = null;
        $apiOrder->status = 'paid';
        $apiOrder->taxSummary = new TaxSummaryCollection([]);
        $apiOrder->lines = [];

        return $apiOrder;
    }

    /**
     * Build a raw API order-line, shaped as the decoded JSON object the
     * api-php {@see ApiOrderLine} hydrates from (so `Order::lines()` rebuilds
     * Money/TaxSummary value objects exactly as in production).
     */
    private function makeApiLine(
        string $id,
        string $description,
        int $quantity,
        string $basePrice,
        string $total,
        string $subtotal,
        ?string $productType,
        ?string $productId,
    ): object {
        return (object) [
            'id' => $id,
            'resource' => 'orderline',
            'description' => $description,
            'quantity' => $quantity,
            'productType' => $productType,
            'productId' => $productId,
            'basePrice' => (object) ['currency' => 'EUR', 'value' => $basePrice],
            'total' => (object) ['currency' => 'EUR', 'value' => $total],
            'subtotal' => (object) ['currency' => 'EUR', 'value' => $subtotal],
            'taxes' => [
                (object) [
                    'taxRate' => (object) ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                    'amount' => (object) ['currency' => 'EUR', 'value' => '4.20'],
                ],
            ],
        ];
    }
}
