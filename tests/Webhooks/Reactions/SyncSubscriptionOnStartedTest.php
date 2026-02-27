<?php

declare(strict_types=1);

use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnStarted;

test('it supports SubscriptionStarted events', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $reaction = new SyncSubscriptionOnStarted($repo);

    $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

    expect($reaction->supports($event))->toBeTrue();
});

test('it does not support other events', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $reaction = new SyncSubscriptionOnStarted($repo);

    $event = new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', null, null);

    expect($reaction->supports($event))->toBeFalse();
});

test('it creates a subscription when none exists', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
    $repo->shouldReceive('create')->once()->with(Mockery::on(function ($attrs) {
        return $attrs['vatly_id'] === 'sub_1'
            && $attrs['customer_id'] === 'cus_1'
            && $attrs['type'] === 'default'
            && $attrs['plan_id'] === 'plan_1'
            && $attrs['name'] === 'Monthly'
            && $attrs['quantity'] === 1;
    }))->andReturn(Mockery::mock(SubscriptionInterface::class));

    $reaction = new SyncSubscriptionOnStarted($repo);
    $reaction->handle(new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1));
});

test('it updates an existing subscription', function () {
    $existing = Mockery::mock(SubscriptionInterface::class);
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
    $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) {
        return $attrs['plan_id'] === 'plan_1' && $attrs['name'] === 'Monthly' && $attrs['quantity'] === 1;
    }))->andReturn($existing);
    $repo->shouldNotReceive('create');

    $reaction = new SyncSubscriptionOnStarted($repo);
    $reaction->handle(new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1));
});
