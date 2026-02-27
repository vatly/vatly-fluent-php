<?php

declare(strict_types=1);

use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;

test('it supports OrderPaid events', function () {
    $repo = Mockery::mock(OrderRepositoryInterface::class);
    $reaction = new StoreOrderOnPaid($repo);

    $event = new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', 'INV-001', 'card');

    expect($reaction->supports($event))->toBeTrue();
});

test('it does not support other events', function () {
    $repo = Mockery::mock(OrderRepositoryInterface::class);
    $reaction = new StoreOrderOnPaid($repo);

    $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

    expect($reaction->supports($event))->toBeFalse();
});

test('it creates an order when none exists', function () {
    $repo = Mockery::mock(OrderRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
    $repo->shouldReceive('create')->once()->with(Mockery::on(function ($attrs) {
        return $attrs['vatly_id'] === 'ord_1'
            && $attrs['customer_id'] === 'cus_1'
            && $attrs['status'] === 'paid'
            && $attrs['total'] === 9900
            && $attrs['currency'] === 'EUR'
            && $attrs['invoice_number'] === 'INV-001'
            && $attrs['payment_method'] === 'card';
    }))->andReturn(Mockery::mock(OrderInterface::class));

    $reaction = new StoreOrderOnPaid($repo);
    $reaction->handle(new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', 'INV-001', 'card'));
});

test('it updates an existing order', function () {
    $existing = Mockery::mock(OrderInterface::class);
    $repo = Mockery::mock(OrderRepositoryInterface::class);
    $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
    $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) {
        return $attrs['status'] === 'paid' && $attrs['total'] === 9900;
    }))->andReturn($existing);
    $repo->shouldNotReceive('create');

    $reaction = new StoreOrderOnPaid($repo);
    $reaction->handle(new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', 'INV-001', 'card'));
});
