<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use DateTimeInterface;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\WebhookEventFactory;

class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new WebhookEventFactory();
    }

    public function test_it_parses_webhook_payload_into_webhook_received_event(): void
    {
        $payload = [
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => ['data' => ['customerId' => 'cus_456']],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => true,
        ];

        $event = $this->factory->parsePayload($payload);

        $this->assertInstanceOf(WebhookReceived::class, $event);
        $this->assertSame('subscription.started', $event->eventName);
        $this->assertSame('sub_123', $event->resourceId);
        $this->assertSame('subscription', $event->resourceName);
        $this->assertTrue($event->testmode);
        $this->assertSame('cus_456', $event->getCustomerId());
    }

    public function test_it_creates_subscription_started_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Premium Plan',
                    'quantity' => 1,
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
    }

    public function test_it_creates_subscription_canceled_immediately_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.canceled_immediately',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCanceledImmediately::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }

    public function test_it_creates_subscription_canceled_with_grace_period_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.canceled_with_grace_period',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'endsAt' => '2024-02-15T10:00:00Z',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCanceledWithGracePeriod::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endsAt);
    }

    public function test_it_creates_order_paid_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'order.paid',
            resourceId: 'ord_123',
            resourceName: 'order',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'total' => 9900,
                    'currency' => 'EUR',
                    'invoiceNumber' => 'INV-2024-001',
                    'paymentMethod' => 'credit_card',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderPaid::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame(9900, $event->total);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('INV-2024-001', $event->invoiceNumber);
        $this->assertSame('credit_card', $event->paymentMethod);
    }

    public function test_it_creates_unsupported_webhook_received_for_unknown_events(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'unknown.event',
            resourceId: 'res_123',
            resourceName: 'unknown',
            object: [],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $event);
        $this->assertSame('unknown.event', $event->eventName);
    }

    public function test_it_returns_list_of_supported_events(): void
    {
        $supported = $this->factory->getSupportedEvents();

        $this->assertContains('subscription.started', $supported);
        $this->assertContains('subscription.canceled_immediately', $supported);
        $this->assertContains('subscription.canceled_with_grace_period', $supported);
        $this->assertContains('order.paid', $supported);
    }

    public function test_it_checks_if_event_is_supported(): void
    {
        $this->assertTrue($this->factory->isSupported('subscription.started'));
        $this->assertTrue($this->factory->isSupported('order.paid'));
        $this->assertFalse($this->factory->isSupported('unknown.event'));
    }
}
