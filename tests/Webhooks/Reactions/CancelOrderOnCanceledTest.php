<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\API\Webhooks\Events\OrderCanceled;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\CancelOrderOnCanceled;

class CancelOrderOnCanceledTest extends TestCase
{
    public function test_it_supports_order_canceled_events(): void
    {
        $reaction = new CancelOrderOnCanceled(Mockery::mock(OrderRepositoryInterface::class));

        $this->assertTrue($reaction->supports(new OrderCanceled('cus_1', 'ord_1', 'canceled')));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new CancelOrderOnCanceled(Mockery::mock(OrderRepositoryInterface::class));

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', self::money(9900), self::money(8182), new TaxSummaryCollection([]), null, null)));
    }

    public function test_it_mirrors_the_canceled_status_onto_the_local_order(): void
    {
        $existing = Mockery::mock(OrderInterface::class);
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateOrderData $data) {
            return $data->status === 'canceled';
        }))->andReturn($existing);

        $reaction = new CancelOrderOnCanceled($repo);
        $reaction->handle(new OrderCanceled('cus_1', 'ord_1', 'canceled'));
    }

    public function test_it_does_nothing_if_order_not_found(): void
    {
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new CancelOrderOnCanceled($repo);
        $reaction->handle(new OrderCanceled('cus_1', 'ord_1', 'canceled'));
    }
}
