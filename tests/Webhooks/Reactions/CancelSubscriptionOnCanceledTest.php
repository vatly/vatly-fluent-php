<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use DateTimeImmutable;
use Mockery;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;

class CancelSubscriptionOnCanceledTest extends TestCase
{
    public function test_it_supports_subscription_canceled_immediately_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $reaction = new CancelSubscriptionOnCanceled($repo);

        $this->assertTrue($reaction->supports(new SubscriptionCanceledImmediately('cus_1', 'sub_1')));
    }

    public function test_it_supports_subscription_canceled_with_grace_period_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $reaction = new CancelSubscriptionOnCanceled($repo);

        $event = new SubscriptionCanceledWithGracePeriod('cus_1', 'sub_1', new DateTimeImmutable('+30 days'));

        $this->assertTrue($reaction->supports($event));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $reaction = new CancelSubscriptionOnCanceled($repo);

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', null, null)));
    }

    public function test_it_sets_ends_at_to_now_for_immediate_cancellation(): void
    {
        $existing = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) {
            return isset($attrs['ends_at']) && $attrs['ends_at'] instanceof DateTimeImmutable;
        }))->andReturn($existing);

        $reaction = new CancelSubscriptionOnCanceled($repo);
        $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1'));
    }

    public function test_it_sets_ends_at_to_grace_period_end_for_grace_period_cancellation(): void
    {
        $endsAt = new DateTimeImmutable('2025-03-15T00:00:00Z');
        $existing = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) use ($endsAt) {
            return $attrs['ends_at'] === $endsAt;
        }))->andReturn($existing);

        $reaction = new CancelSubscriptionOnCanceled($repo);
        $reaction->handle(new SubscriptionCanceledWithGracePeriod('cus_1', 'sub_1', $endsAt));
    }

    public function test_it_does_nothing_if_subscription_not_found(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new CancelSubscriptionOnCanceled($repo);
        $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1'));
    }
}
