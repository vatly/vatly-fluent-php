<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use DateTimeImmutable;
use Mockery;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately;
use Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;

class CancelSubscriptionOnCanceledTest extends TestCase
{
    public function test_it_supports_subscription_canceled_immediately_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $reaction = new CancelSubscriptionOnCanceled($repo);

        $event = new SubscriptionCanceledImmediately('cus_1', 'sub_1', new DateTimeImmutable());

        $this->assertTrue($reaction->supports($event));
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

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', 9900, 8182, new TaxSummaryCollection([]), 'EUR', null, null)));
    }

    public function test_it_persists_the_event_ends_at_for_immediate_cancellation(): void
    {
        $endsAt = new DateTimeImmutable('2026-05-20T10:00:00Z');
        $existing = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) use ($endsAt) {
            return $data->endsAt === $endsAt;
        }))->andReturn($existing);

        $reaction = new CancelSubscriptionOnCanceled($repo);
        $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1', $endsAt));
    }

    public function test_it_persists_the_event_ends_at_for_grace_period_cancellation(): void
    {
        $endsAt = new DateTimeImmutable('2025-03-15T00:00:00Z');
        $existing = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) use ($endsAt) {
            return $data->endsAt === $endsAt;
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
        $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1', new DateTimeImmutable()));
    }
}
