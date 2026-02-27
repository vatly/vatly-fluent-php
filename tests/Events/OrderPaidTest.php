<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

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
            total: 9900,
            currency: 'EUR',
            invoiceNumber: 'INV-2024-001',
            paymentMethod: 'credit_card',
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('ord_456', $event->orderId);
        $this->assertSame(9900, $event->total);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('INV-2024-001', $event->invoiceNumber);
        $this->assertSame('credit_card', $event->paymentMethod);
    }

    public function test_it_creates_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'order.paid',
            resourceId: 'ord_123',
            resourceName: 'order',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'total' => 4999,
                    'currency' => 'USD',
                    'invoiceNumber' => 'INV-2024-002',
                    'paymentMethod' => 'ideal',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = OrderPaid::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame(4999, $event->total);
        $this->assertSame('USD', $event->currency);
        $this->assertSame('INV-2024-002', $event->invoiceNumber);
        $this->assertSame('ideal', $event->paymentMethod);
    }

    public function test_it_creates_from_webhook_with_nullable_fields(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'order.paid',
            resourceId: 'ord_789',
            resourceName: 'order',
            object: [
                'data' => [
                    'customerId' => 'cus_012',
                    'total' => 1500,
                    'currency' => 'GBP',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = OrderPaid::fromWebhook($webhook);

        $this->assertSame('cus_012', $event->customerId);
        $this->assertSame('ord_789', $event->orderId);
        $this->assertSame(1500, $event->total);
        $this->assertSame('GBP', $event->currency);
        $this->assertNull($event->invoiceNumber);
        $this->assertNull($event->paymentMethod);
    }
}
