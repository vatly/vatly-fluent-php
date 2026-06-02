<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Resources\Links\OrderLinks;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\RefundInterface;
use Vatly\Fluent\Contracts\RefundReader;
use Vatly\Fluent\OrderHandle;

class OrderHandleTest extends TestCase
{
    public function test_state_accessors_delegate_to_the_underlying_order(): void
    {
        $order = Mockery::mock(OrderInterface::class);
        $order->shouldReceive('getVatlyId')->andReturn('order_abc');
        $order->shouldReceive('getStatus')->andReturn('paid');
        $order->shouldReceive('getTotal')->andReturn(1999);
        $order->shouldReceive('getSubtotal')->andReturn(1652);
        $order->shouldReceive('getCurrency')->andReturn('EUR');
        $order->shouldReceive('getInvoiceNumber')->andReturn('INV-2026-0001');
        $order->shouldReceive('getPaymentMethod')->andReturn('creditcard');
        $order->shouldReceive('isPaid')->andReturn(true);

        $handle = new OrderHandle(
            order: $order,
            getOrderAction: Mockery::mock(GetOrder::class),
        );

        $this->assertSame('order_abc', $handle->getVatlyId());
        $this->assertSame('paid', $handle->getStatus());
        $this->assertSame(1999, $handle->getTotal());
        $this->assertSame(1652, $handle->getSubtotal());
        $this->assertSame('EUR', $handle->getCurrency());
        $this->assertSame('INV-2026-0001', $handle->getInvoiceNumber());
        $this->assertSame('creditcard', $handle->getPaymentMethod());
        $this->assertTrue($handle->isPaid());
        $this->assertSame($order, $handle->model());
    }

    public function test_invoice_url_returns_the_customer_invoice_href_when_present(): void
    {
        $apiOrder = $this->buildApiOrder(invoiceHref: 'https://invoices.vatly.test/inv_123.pdf');

        $getOrder = Mockery::mock(GetOrder::class);
        $getOrder->shouldReceive('execute')->with('order_abc')->andReturn($apiOrder);

        $order = Mockery::mock(OrderInterface::class);
        $order->shouldReceive('getVatlyId')->andReturn('order_abc');

        $handle = new OrderHandle(order: $order, getOrderAction: $getOrder);

        $this->assertSame('https://invoices.vatly.test/inv_123.pdf', $handle->invoiceUrl());
    }

    public function test_invoice_url_is_null_when_no_customer_invoice_link(): void
    {
        $apiOrder = $this->buildApiOrder(invoiceHref: null);

        $getOrder = Mockery::mock(GetOrder::class);
        $getOrder->shouldReceive('execute')->with('order_abc')->andReturn($apiOrder);

        $order = Mockery::mock(OrderInterface::class);
        $order->shouldReceive('getVatlyId')->andReturn('order_abc');

        $handle = new OrderHandle(order: $order, getOrderAction: $getOrder);

        $this->assertNull($handle->invoiceUrl());
    }

    public function test_refunds_returns_local_refunds_for_the_order(): void
    {
        $refundA = Mockery::mock(RefundInterface::class);
        $refundB = Mockery::mock(RefundInterface::class);

        $reader = Mockery::mock(RefundReader::class);
        $reader->shouldReceive('listForOrder')->with('order_abc')->once()->andReturn([$refundA, $refundB]);

        $order = Mockery::mock(OrderInterface::class);
        $order->shouldReceive('getVatlyId')->andReturn('order_abc');

        $handle = new OrderHandle(
            order: $order,
            getOrderAction: Mockery::mock(GetOrder::class),
            refunds: $reader,
        );

        $this->assertSame([$refundA, $refundB], $handle->refunds());
    }

    public function test_refunds_is_empty_when_no_refund_reader_is_wired(): void
    {
        $order = Mockery::mock(OrderInterface::class);

        $handle = new OrderHandle(
            order: $order,
            getOrderAction: Mockery::mock(GetOrder::class),
        );

        $this->assertSame([], $handle->refunds());
    }

    private function buildApiOrder(?string $invoiceHref): ApiOrder
    {
        $client = Mockery::mock(VatlyApiClient::class);

        $apiOrder = new ApiOrder($client);
        $apiOrder->id = 'order_abc';

        $apiOrder->links = new OrderLinks();
        $apiOrder->links->customerInvoice = $invoiceHref !== null
            ? new Link($invoiceHref, 'text/html')
            : null;

        return $apiOrder;
    }
}
