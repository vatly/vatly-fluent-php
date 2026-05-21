<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\StoreSubscriptionData;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Events\LocalSubscriptionCreated;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\TaxSummary;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnStarted;

class SyncSubscriptionOnStartedTest extends TestCase
{
    public function test_it_supports_subscription_started_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $reaction = new SyncSubscriptionOnStarted($repo, $dispatcher);

        $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

        $this->assertTrue($reaction->supports($event));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $reaction = new SyncSubscriptionOnStarted($repo, $dispatcher);

        $event = new OrderPaid('cus_1', 'ord_1', 9900, 8182, TaxSummary::empty(), 'EUR', null, null);

        $this->assertFalse($reaction->supports($event));
    }

    public function test_it_stores_a_subscription_when_none_exists(): void
    {
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(function (StoreSubscriptionData $data) {
            return $data->vatlyId === 'sub_1'
                && $data->customerId === 'cus_1'
                && $data->type === 'default'
                && $data->planId === 'plan_1'
                && $data->name === 'Monthly'
                && $data->quantity === 1;
        }))->andReturn($subscription);

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) use ($subscription) {
            return $event instanceof LocalSubscriptionCreated
                && $event->subscription === $subscription;
        }));

        $reaction = new SyncSubscriptionOnStarted($repo, $dispatcher);
        $reaction->handle(new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1));
    }

    public function test_it_updates_an_existing_subscription(): void
    {
        $existing = Mockery::mock(SubscriptionInterface::class);
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) {
            return $data->planId === 'plan_1' && $data->name === 'Monthly' && $data->quantity === 1;
        }))->andReturn($existing);
        $repo->shouldNotReceive('store');

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        $reaction = new SyncSubscriptionOnStarted($repo, $dispatcher);
        $reaction->handle(new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1));
    }
}
