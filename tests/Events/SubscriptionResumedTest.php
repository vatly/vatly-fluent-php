<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\SubscriptionResumed;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionResumedTest extends TestCase
{
    public function test_it_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('subscription.resumed', SubscriptionResumed::VATLY_EVENT_NAME);
    }

    public function test_it_can_be_instantiated_with_properties(): void
    {
        $event = new SubscriptionResumed(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('sub_456', $event->subscriptionId);
    }

    public function test_it_creates_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.resumed',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = SubscriptionResumed::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }
}
