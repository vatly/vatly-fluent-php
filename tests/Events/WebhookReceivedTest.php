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
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: ['customerId' => 'cus_456'],
        );

        $this->assertSame('webhook_event_abc', $event->id);
        $this->assertSame('webhook_event', $event->resource);
        $this->assertSame('subscription.started', $event->eventName);
        $this->assertSame('subscription', $event->entityType);
        $this->assertSame('sub_123', $event->entityId);
        $this->assertSame(['customerId' => 'cus_456'], $event->object);
    }

    public function test_it_converts_to_array(): void
    {
        $event = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: [],
        );

        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('resource', $array);
        $this->assertArrayHasKey('eventName', $array);
        $this->assertArrayHasKey('entityType', $array);
        $this->assertArrayHasKey('entityId', $array);
        $this->assertArrayHasKey('object', $array);
        $this->assertSame('subscription.started', $array['eventName']);
    }

    public function test_it_extracts_customer_id_from_object(): void
    {
        $event = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: ['customerId' => 'cus_456'],
        );

        $this->assertSame('cus_456', $event->getCustomerId());
    }

    public function test_it_returns_null_when_customer_id_not_present(): void
    {
        $event = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'test.event',
            entityType: 'resource',
            entityId: 'res_123',
            object: [],
        );

        $this->assertNull($event->getCustomerId());
    }
}
