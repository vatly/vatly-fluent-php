<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\OrderChargebackReceived;
use Vatly\Fluent\Events\OrderChargebackReversed;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class ChargebackEventsTest extends TestCase
{
    public function test_received_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.chargeback_received', OrderChargebackReceived::VATLY_EVENT_NAME);
    }

    public function test_reversed_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.chargeback_reversed', OrderChargebackReversed::VATLY_EVENT_NAME);
    }

    public function test_received_creates_from_webhook_with_order_id_from_entity_id(): void
    {
        $event = OrderChargebackReceived::fromWebhook($this->webhook('order.chargeback_received', [
            'id' => 'chargeback_789',
            'resource' => 'chargeback',
            'originalOrderId' => 'ord_original_1',
            'reason' => 'fraudulent',
        ]));

        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame('fraudulent', $event->reason);
    }

    public function test_reversed_creates_from_webhook_with_null_reason_when_absent(): void
    {
        $event = OrderChargebackReversed::fromWebhook($this->webhook('order.chargeback_reversed', [
            'id' => 'chargeback_789',
            'resource' => 'chargeback',
            'originalOrderId' => 'ord_original_1',
        ]));

        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertNull($event->reason);
    }

    public function test_original_order_id_falls_back_to_entity_id_when_absent(): void
    {
        $event = OrderChargebackReceived::fromWebhook($this->webhook('order.chargeback_received', [
            'id' => 'chargeback_789',
        ]));

        $this->assertSame('ord_123', $event->originalOrderId);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function webhook(string $eventName, array $object): WebhookReceived
    {
        return new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: $eventName,
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: $object,
        );
    }
}
