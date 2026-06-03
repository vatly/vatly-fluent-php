<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use DateTimeInterface;
use Vatly\Fluent\Concerns\DerivesSubscriptionState;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Events\SubscriptionWasCreatedFromWebhook;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionWasCreatedFromWebhookTest extends TestCase
{
    public function test_it_can_be_instantiated_with_subscription(): void
    {
        $subscription = $this->createMockSubscription();

        $event = new SubscriptionWasCreatedFromWebhook($subscription);

        $this->assertSame($subscription, $event->subscription);
        $this->assertInstanceOf(SubscriptionInterface::class, $event->subscription);
    }

    public function test_it_provides_access_to_subscription_properties(): void
    {
        $subscription = $this->createMockSubscription();

        $event = new SubscriptionWasCreatedFromWebhook($subscription);

        $this->assertSame('sub_test_123', $event->subscription->getVatlyId());
        $this->assertTrue($event->subscription->isActive());
    }

    private function createMockSubscription(): SubscriptionInterface
    {
        return new class implements SubscriptionInterface {
            use DerivesSubscriptionState;

            public function getVatlyId(): string
            {
                return 'sub_test_123';
            }

            public function getType(): string
            {
                return 'default';
            }

            public function getPlanId(): string
            {
                return 'plan_abc';
            }

            public function getName(): string
            {
                return 'Test Subscription';
            }

            public function getQuantity(): int
            {
                return 1;
            }

            public function getEndsAt(): ?DateTimeInterface
            {
                return null;
            }

            public function getMandateMethod(): ?string
            {
                return null;
            }

            public function getMandateMaskedIdentifier(): ?string
            {
                return null;
            }
        };
    }
}
