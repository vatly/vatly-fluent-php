<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class UnsupportedWebhookReceivedTest extends TestCase
{
    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $event = new UnsupportedWebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'unknown.event',
            entityType: 'resource',
            entityId: 'res_123',
            testmode: true,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['data' => ['key' => 'value']],
        );

        $this->assertSame('webhook_event_abc', $event->id);
        $this->assertSame('webhook_event', $event->resource);
        $this->assertSame('unknown.event', $event->eventName);
        $this->assertSame('resource', $event->entityType);
        $this->assertSame('res_123', $event->entityId);
        $this->assertTrue($event->testmode);
        $this->assertSame('2024-01-15T10:00:00Z', $event->createdAt);
        $this->assertSame(['data' => ['key' => 'value']], $event->object);
    }

    public function test_it_creates_from_webhook_received(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_xyz',
            resource: 'webhook_event',
            eventName: 'unknown.event.type',
            entityType: 'unknown_resource',
            entityId: 'xyz_789',
            testmode: false,
            createdAt: '2024-06-01T12:00:00Z',
            object: ['foo' => 'bar'],
        );

        $event = UnsupportedWebhookReceived::fromWebhook($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $event);
        $this->assertSame('webhook_event_xyz', $event->id);
        $this->assertSame('webhook_event', $event->resource);
        $this->assertSame('unknown.event.type', $event->eventName);
        $this->assertSame('unknown_resource', $event->entityType);
        $this->assertSame('xyz_789', $event->entityId);
        $this->assertFalse($event->testmode);
        $this->assertSame('2024-06-01T12:00:00Z', $event->createdAt);
        $this->assertSame(['foo' => 'bar'], $event->object);
    }
}
