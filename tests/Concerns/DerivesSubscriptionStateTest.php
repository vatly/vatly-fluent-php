<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use Vatly\Fluent\Concerns\DerivesSubscriptionState;
use Vatly\Fluent\Tests\TestCase;

class DerivesSubscriptionStateTest extends TestCase
{
    public function test_subscription_with_no_ends_at_is_active_and_recurring(): void
    {
        $sub = $this->subscription(endsAt: null);

        $this->assertFalse($sub->isCancelled());
        $this->assertFalse($sub->isOnGracePeriod());
        $this->assertTrue($sub->isActive());
        $this->assertTrue($sub->isValid());
        $this->assertTrue($sub->isRecurring());
        $this->assertFalse($sub->isEnded());
    }

    public function test_subscription_with_future_ends_at_is_on_grace_period(): void
    {
        $sub = $this->subscription(endsAt: new DateTimeImmutable('+7 days'));

        $this->assertTrue($sub->isCancelled());
        $this->assertTrue($sub->isOnGracePeriod());
        $this->assertTrue($sub->isActive());
        $this->assertTrue($sub->isValid());
        $this->assertFalse($sub->isRecurring());
        $this->assertFalse($sub->isEnded());
    }

    public function test_subscription_with_past_ends_at_is_ended(): void
    {
        $sub = $this->subscription(endsAt: new DateTimeImmutable('-7 days'));

        $this->assertTrue($sub->isCancelled());
        $this->assertFalse($sub->isOnGracePeriod());
        $this->assertFalse($sub->isActive());
        $this->assertFalse($sub->isValid());
        $this->assertFalse($sub->isRecurring());
        $this->assertTrue($sub->isEnded());
    }

    /**
     * Build a minimal object exposing `getEndsAt()` and the
     * trait's predicates. No SubscriptionInterface needed — we're testing
     * the trait in isolation.
     */
    private function subscription(?DateTimeInterface $endsAt): object
    {
        return new class($endsAt) {
            use DerivesSubscriptionState;

            public function __construct(private ?DateTimeInterface $endsAt) {}

            public function getEndsAt(): ?DateTimeInterface
            {
                return $this->endsAt;
            }
        };
    }
}
