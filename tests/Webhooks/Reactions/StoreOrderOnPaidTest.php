<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;

class StoreOrderOnPaidTest extends TestCase
{
    public function test_it_supports_order_paid_events(): void
    {
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $reaction = new StoreOrderOnPaid($repo);

        $event = new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', 'INV-001', 'card');

        $this->assertTrue($reaction->supports($event));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $reaction = new StoreOrderOnPaid($repo);

        $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

        $this->assertFalse($reaction->supports($event));
    }

    public function test_it_creates_an_order_when_none_exists(): void
    {
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
    }

    public function test_it_updates_an_existing_order(): void
    {
        $existing = Mockery::mock(OrderInterface::class);
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function ($attrs) {
            return $attrs['status'] === 'paid' && $attrs['total'] === 9900;
        }))->andReturn($existing);
        $repo->shouldNotReceive('create');

        $reaction = new StoreOrderOnPaid($repo);
        $reaction->handle(new OrderPaid('cus_1', 'ord_1', 9900, 'EUR', 'INV-001', 'card'));
    }
}
