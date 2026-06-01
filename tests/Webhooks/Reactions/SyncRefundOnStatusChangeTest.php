<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\RefundInterface;
use Vatly\Fluent\Contracts\RefundRepositoryInterface;
use Vatly\Fluent\Data\StoreRefundData;
use Vatly\Fluent\Data\UpdateRefundData;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\RefundCanceled;
use Vatly\Fluent\Events\RefundCompleted;
use Vatly\Fluent\Events\RefundFailed;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\TaxSummary;
use Vatly\Fluent\Webhooks\Reactions\SyncRefundOnStatusChange;

class SyncRefundOnStatusChangeTest extends TestCase
{
    public function test_it_supports_all_three_refund_events(): void
    {
        $reaction = new SyncRefundOnStatusChange(
            Mockery::mock(RefundRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertTrue($reaction->supports($this->event(RefundCompleted::class, 'refunded')));
        $this->assertTrue($reaction->supports($this->event(RefundFailed::class, 'failed')));
        $this->assertTrue($reaction->supports($this->event(RefundCanceled::class, 'canceled')));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new SyncRefundOnStatusChange(
            Mockery::mock(RefundRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', 9900, 8182, TaxSummary::empty(), 'EUR', null, null)));
    }

    public function test_it_updates_an_existing_refund_with_the_new_status(): void
    {
        $existing = Mockery::mock(RefundInterface::class);
        $repo = Mockery::mock(RefundRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('refund_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateRefundData $data) {
            return $data->status === 'refunded' && $data->total === 9900 && $data->currency === 'EUR';
        }))->andReturn($existing);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('record');

        $reaction = new SyncRefundOnStatusChange($repo, $bindings);
        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    public function test_it_stores_a_new_refund_resolving_host_customer_from_bindings(): void
    {
        $stored = Mockery::mock(RefundInterface::class);
        $repo = Mockery::mock(RefundRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('refund_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(function (StoreRefundData $data) {
            return $data->vatlyId === 'refund_1'
                && $data->customerId === 'cus_1'
                && $data->status === 'refunded'
                && $data->total === 9900
                && $data->originalOrderId === 'ord_original_1'
                && $data->hostCustomerId === 'host_42';
        }))->andReturn($stored);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_1')->once()->andReturn('host_42');
        $bindings->shouldReceive('record')->with('cus_1')->once();

        $reaction = new SyncRefundOnStatusChange($repo, $bindings);
        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    /**
     * @param class-string $class
     */
    private function event(string $class, string $status): object
    {
        return new $class(
            'cus_1',
            'refund_1',
            $status,
            9900,
            8182,
            TaxSummary::empty(),
            'EUR',
            'ord_original_1',
        );
    }
}
