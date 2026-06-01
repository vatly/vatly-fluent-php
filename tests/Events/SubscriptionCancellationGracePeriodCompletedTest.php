<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use DateTimeInterface;
use Vatly\Fluent\Events\SubscriptionCancellationGracePeriodCompleted;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionCancellationGracePeriodCompletedTest extends TestCase
{
    public function test_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame(
            'subscription.cancellation_grace_period_completed',
            SubscriptionCancellationGracePeriodCompleted::VATLY_EVENT_NAME,
        );
    }

    public function test_creates_from_webhook_with_ended_at_timestamp(): void
    {
        $event = SubscriptionCancellationGracePeriodCompleted::fromWebhook($this->webhook([
            'customerId' => 'cus_456',
            'endedAt' => '2024-02-15T10:00:00Z',
        ]));

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endsAt);
        $this->assertSame('2024-02-15T10:00:00+00:00', $event->endsAt->format('c'));
    }

    public function test_ends_at_falls_back_to_created_at_when_ended_at_absent(): void
    {
        $event = SubscriptionCancellationGracePeriodCompleted::fromWebhook($this->webhook([
            'customerId' => 'cus_456',
        ]));

        $this->assertSame('2024-01-15T10:00:00+00:00', $event->endsAt->format('c'));
    }

    public function test_customer_id_falls_back_to_empty_string_when_absent(): void
    {
        $event = SubscriptionCancellationGracePeriodCompleted::fromWebhook($this->webhook([]));

        $this->assertSame('', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function webhook(array $object): WebhookReceived
    {
        return new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.cancellation_grace_period_completed',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: $object,
        );
    }
}
