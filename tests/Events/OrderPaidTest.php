<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
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

        return $apiOrder;
    }
}
