<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use DateTimeInterface;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Events\LocalSubscriptionCreated;
use Vatly\Fluent\Tests\TestCase;

class LocalSubscriptionCreatedTest extends TestCase
{
    public function test_it_can_be_instantiated_with_subscription(): void
    {
        $subscription = $this->createMockSubscription();

        $event = new LocalSubscriptionCreated($subscription);

        $this->assertSame($subscription, $event->subscription);
        $this->assertInstanceOf(SubscriptionInterface::class, $event->subscription);
    }

    public function test_it_provides_access_to_subscription_properties(): void
    {
        $subscription = $this->createMockSubscription();

        $event = new LocalSubscriptionCreated($subscription);

        $this->assertSame('sub_test_123', $event->subscription->getVatlyId());
        $this->assertTrue($event->subscription->isActive());
    }

    private function createMockSubscription(): SubscriptionInterface
    {
        return new class implements SubscriptionInterface {
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

            public function isActive(): bool
            {
                return true;
            }

            public function isCancelled(): bool
            {
                return false;
            }

            public function isOnGracePeriod(): bool
            {
                return false;
            }

            public function getOwner(): BillableInterface
            {
                return new class implements BillableInterface {
                    public function getVatlyId(): ?string
                    {
                        return 'cus_123';
                    }

                    public function setVatlyId(string $id): void {}

                    public function hasVatlyId(): bool
                    {
                        return true;
                    }

                    public function getVatlyEmail(): ?string
                    {
                        return 'test@example.com';
                    }

                    public function getVatlyName(): ?string
                    {
                        return 'Test User';
                    }

                    public function getKey(): string|int
                    {
                        return 1;
                    }

                    public function save(): mixed
                    {
                        return true;
                    }
                };
            }
        };
    }
}
