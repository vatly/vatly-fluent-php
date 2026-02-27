<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionStartedTest extends TestCase
{
    public function test_it_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('subscription.started', SubscriptionStarted::VATLY_EVENT_NAME);
    }

    public function test_it_has_default_type_constant(): void
    {
        $this->assertSame('default', SubscriptionStarted::DEFAULT_TYPE);
    }

    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $event = new SubscriptionStarted(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
            planId: 'plan_789',
            type: 'premium',
            name: 'Premium Plan',
            quantity: 2,
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('sub_456', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('premium', $event->type);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(2, $event->quantity);
    }

    public function test_it_creates_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Basic Plan',
                    'quantity' => 1,
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = SubscriptionStarted::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('default', $event->type);
        $this->assertSame('Basic Plan', $event->name);
        $this->assertSame(1, $event->quantity);
    }
}
