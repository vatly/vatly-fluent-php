<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class WebhookReceivedTest extends TestCase
{
    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $event = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: ['data' => ['customerId' => 'cus_456']],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: true,
        );

        $this->assertSame('subscription.started', $event->eventName);
        $this->assertSame('sub_123', $event->resourceId);
        $this->assertSame('subscription', $event->resourceName);
        $this->assertSame(['data' => ['customerId' => 'cus_456']], $event->object);
        $this->assertSame('2024-01-15T10:00:00Z', $event->raisedAt);
        $this->assertTrue($event->testmode);
    }

    public function test_it_converts_to_array(): void
    {
        $event = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: ['data' => []],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $array = $event->toArray();

        $this->assertArrayHasKey('eventName', $array);
        $this->assertArrayHasKey('resourceId', $array);
        $this->assertArrayHasKey('resourceName', $array);
        $this->assertArrayHasKey('object', $array);
        $this->assertArrayHasKey('raisedAt', $array);
        $this->assertArrayHasKey('testmode', $array);
        $this->assertSame('subscription.started', $array['eventName']);
    }

    public function test_it_extracts_customer_id_from_object(): void
    {
        $event = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: ['data' => ['customerId' => 'cus_456']],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $this->assertSame('cus_456', $event->getCustomerId());
    }

    public function test_it_returns_null_when_customer_id_not_present(): void
    {
        $event = new WebhookReceived(
            eventName: 'test.event',
            resourceId: 'res_123',
            resourceName: 'resource',
            object: ['data' => []],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $this->assertNull($event->getCustomerId());
    }
}
