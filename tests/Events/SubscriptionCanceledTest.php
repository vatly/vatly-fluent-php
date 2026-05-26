<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use DateTimeImmutable;
use DateTimeInterface;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionCanceledTest extends TestCase
{
    public function test_immediately_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('subscription.canceled_immediately', SubscriptionCanceledImmediately::VATLY_EVENT_NAME);
    }

    public function test_immediately_can_be_instantiated_with_properties(): void
    {
        $endsAt = new DateTimeImmutable('2024-01-15T10:00:00Z');

        $event = new SubscriptionCanceledImmediately(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
            endsAt: $endsAt,
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('sub_456', $event->subscriptionId);
        $this->assertSame($endsAt, $event->endsAt);
    }

    public function test_immediately_creates_from_webhook_using_object_ends_at(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.canceled_immediately',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'endsAt' => '2024-01-15T10:00:00Z',
                ],
            ],
        );

        $event = SubscriptionCanceledImmediately::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertEquals(
            new DateTimeImmutable('2024-01-15T10:00:00Z'),
            $event->endsAt,
        );
    }

    public function test_immediately_falls_back_to_now_when_object_lacks_ends_at(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.canceled_immediately',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: ['data' => ['customerId' => 'cus_456']],
        );

        $before = new DateTimeImmutable();
        $event = SubscriptionCanceledImmediately::fromWebhook($webhook);
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->endsAt);
        $this->assertLessThanOrEqual($after, $event->endsAt);
    }

    public function test_with_grace_period_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('subscription.canceled_with_grace_period', SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME);
    }

    public function test_with_grace_period_can_be_instantiated_with_properties(): void
    {
        $endsAt = new DateTimeImmutable('2024-02-15T10:00:00Z');

        $event = new SubscriptionCanceledWithGracePeriod(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
            endsAt: $endsAt,
        );

        $this->assertSame('cus_123', $event->customerId);
        $this->assertSame('sub_456', $event->subscriptionId);
        $this->assertSame($endsAt, $event->endsAt);
    }

    public function test_with_grace_period_creates_from_webhook_with_parsed_date(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.canceled_with_grace_period',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'endsAt' => '2024-02-15T10:00:00Z',
                ],
            ],
        );

        $event = SubscriptionCanceledWithGracePeriod::fromWebhook($webhook);

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endsAt);
        $this->assertSame('2024-02-15', $event->endsAt->format('Y-m-d'));
    }
}
