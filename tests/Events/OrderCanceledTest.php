<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\OrderCanceled;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class OrderCanceledTest extends TestCase
{
    public function test_it_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.canceled', OrderCanceled::VATLY_EVENT_NAME);
    }

    public function test_it_creates_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'order.canceled',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456', 'status' => 'canceled'],
        );

        $event = OrderCanceled::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('canceled', $event->status);
    }

    public function test_it_defaults_status_and_customer_when_absent_from_payload(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'order.canceled',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $event = OrderCanceled::fromWebhook($webhook);

        $this->assertSame('', $event->customerId);
        $this->assertSame('canceled', $event->status);
    }
}
