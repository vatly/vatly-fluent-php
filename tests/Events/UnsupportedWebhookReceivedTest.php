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
            eventName: 'unknown.event',
            resourceId: 'res_123',
            resourceName: 'resource',
            object: ['data' => ['key' => 'value']],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: true,
        );

        $this->assertSame('unknown.event', $event->eventName);
        $this->assertSame('res_123', $event->resourceId);
        $this->assertSame('resource', $event->resourceName);
        $this->assertSame(['data' => ['key' => 'value']], $event->object);
        $this->assertSame('2024-01-15T10:00:00Z', $event->raisedAt);
        $this->assertTrue($event->testmode);
    }

    public function test_it_creates_from_webhook_received(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'unknown.event.type',
            resourceId: 'xyz_789',
            resourceName: 'unknown_resource',
            object: ['foo' => 'bar'],
            raisedAt: '2024-06-01T12:00:00Z',
            testmode: false,
        );

        $event = UnsupportedWebhookReceived::fromWebhook($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $event);
        $this->assertSame('unknown.event.type', $event->eventName);
        $this->assertSame('xyz_789', $event->resourceId);
        $this->assertSame('unknown_resource', $event->resourceName);
        $this->assertSame(['foo' => 'bar'], $event->object);
        $this->assertSame('2024-06-01T12:00:00Z', $event->raisedAt);
        $this->assertFalse($event->testmode);
    }
}
