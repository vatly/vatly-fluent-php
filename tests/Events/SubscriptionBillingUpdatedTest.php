<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Mockery;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Events\SubscriptionBillingUpdated;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionBillingUpdatedTest extends TestCase
{
    public function test_it_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('subscription.billing_updated', SubscriptionBillingUpdated::VATLY_EVENT_NAME);
    }

    public function test_it_can_be_instantiated_with_all_properties(): void
    {
        $mandate = new Mandate('card', '4242');

        $event = new SubscriptionBillingUpdated(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
            planId: 'plan_789',
            name: 'Premium Plan',
            quantity: 2,
            mandate: $mandate,
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('sub_456', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(2, $event->quantity);
        $this->assertSame($mandate, $event->mandate);
    }

    public function test_it_creates_from_api_subscription_with_mandate(): void
    {
        $mandate = new Mandate('sepa_debit', 'NL91****4300');

        $subscription = new ApiSubscription(Mockery::mock(VatlyApiClient::class));
        $subscription->id = 'sub_123';
        $subscription->customerId = 'cus_456';
        $subscription->subscriptionPlanId = 'plan_789';
        $subscription->name = 'Premium Plan';
        $subscription->quantity = 3;
        $subscription->mandate = $mandate;

        $event = SubscriptionBillingUpdated::fromApiSubscription($subscription);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(3, $event->quantity);
        $this->assertSame($mandate, $event->mandate);
    }

    public function test_it_creates_from_webhook_with_null_mandate(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.billing_updated',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Basic Plan',
                'quantity' => 1,
            ],
        );

        $event = SubscriptionBillingUpdated::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Basic Plan', $event->name);
        $this->assertSame(1, $event->quantity);
        $this->assertNull($event->mandate);
    }
}
