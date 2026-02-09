<?php

declare(strict_types=1);

use Vatly\Contracts\SubscriptionInterface;
use Vatly\Events\LocalSubscriptionCreated;

test('it can be instantiated with subscription', function () {
    $subscription = createMockSubscription();

    $event = new LocalSubscriptionCreated($subscription);

    expect($event->subscription)->toBe($subscription)
        ->and($event->subscription)->toBeInstanceOf(SubscriptionInterface::class);
});

test('it provides access to subscription properties', function () {
    $subscription = createMockSubscription();

    $event = new LocalSubscriptionCreated($subscription);

    expect($event->subscription->getVatlyId())->toBe('sub_test_123')
        ->and($event->subscription->isActive())->toBeTrue();
});

/**
 * Helper to create a mock subscription.
 */
function createMockSubscription(): SubscriptionInterface
{
    return new class implements SubscriptionInterface {
        public function getVatlyId(): ?string
        {
            return 'sub_test_123';
        }

        public function getType(): string
        {
            return 'default';
        }

        public function getPlanId(): ?string
        {
            return 'plan_abc';
        }

        public function getStatus(): string
        {
            return 'active';
        }

        public function getQuantity(): int
        {
            return 1;
        }

        public function getEndsAt(): ?\DateTimeInterface
        {
            return null;
        }

        public function isActive(): bool
        {
            return true;
        }

        public function isCanceled(): bool
        {
            return false;
        }

        public function isOnGracePeriod(): bool
        {
            return false;
        }

        public function markAsCanceled(?\DateTimeInterface $endsAt = null): void
        {
            // Mock implementation
        }
    };
}
