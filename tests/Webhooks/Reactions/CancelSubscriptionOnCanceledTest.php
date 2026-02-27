<?php

declare(strict_types=1);

use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;

test('it supports SubscriptionCanceledImmediately events', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $reaction = new CancelSubscriptionOnCanceled($repo);

    expect($reaction->supports(new SubscriptionCanceledImmediately('cus_1', 'sub_1')))->toBeTrue();
});

test('it supports SubscriptionCanceledWithGracePeriod events', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $reaction = new CancelSubscriptionOnCanceled($repo);

    $event = new SubscriptionCanceledWithGracePeriod('cus_1', 'sub_1', new DateTimeImmutable('+30 days'));

    expect($reaction->supports($event))->toBeTrue();
});

test('it does not support other events', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $reaction = new CancelSubscriptionOnCanceled($repo);

    expect($reaction->supports(new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', null, null)))->toBeFalse();
});

test('it sets ends_at to now for immediate cancellation', function () {
    $existing = Mockery::mock(SubscriptionInterface::class);
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
    $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) {
        return isset($attrs['ends_at']) && $attrs['ends_at'] instanceof DateTimeImmutable;
    }))->andReturn($existing);

    $reaction = new CancelSubscriptionOnCanceled($repo);
    $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1'));
});

test('it sets ends_at to grace period end for grace period cancellation', function () {
    $endsAt = new DateTimeImmutable('2025-03-15T00:00:00Z');
    $existing = Mockery::mock(SubscriptionInterface::class);
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
    $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) use ($endsAt) {
        return $attrs['ends_at'] === $endsAt;
    }))->andReturn($existing);

    $reaction = new CancelSubscriptionOnCanceled($repo);
    $reaction->handle(new SubscriptionCanceledWithGracePeriod('cus_1', 'sub_1', $endsAt));
});

test('it does nothing if subscription not found', function () {
    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
    $repo->shouldNotReceive('update');

    $reaction = new CancelSubscriptionOnCanceled($repo);
    $reaction->handle(new SubscriptionCanceledImmediately('cus_1', 'sub_1'));
});
