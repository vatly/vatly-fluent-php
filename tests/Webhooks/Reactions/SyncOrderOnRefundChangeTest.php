<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\RefundReader;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\RefundCanceled;
use Vatly\Fluent\Events\RefundCompleted;
use Vatly\Fluent\Events\RefundFailed;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\LocalOrderStatus;
use Vatly\Fluent\Types\TaxSummary;
use Vatly\Fluent\Webhooks\Reactions\SyncOrderOnRefundChange;

class SyncOrderOnRefundChangeTest extends TestCase
{
    public function test_it_supports_completed_and_canceled_but_not_failed(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(RefundReader::class),
        );

        $this->assertTrue($reaction->supports($this->event(RefundCompleted::class, 'refunded')));
        $this->assertTrue($reaction->supports($this->event(RefundCanceled::class, 'canceled')));
        // RefundFailed never moves funds, so it cannot change the order's refunded total.
        $this->assertFalse($reaction->supports($this->event(RefundFailed::class, 'failed')));
    }

    public function test_full_refund_marks_the_order_refunded(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            $this->ordersExpecting(orderSubtotal: 8182, expectedStatus: LocalOrderStatus::REFUNDED),
            $this->refundsSumming(8182),
        );

        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    public function test_partial_refund_marks_the_order_partially_refunded(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            $this->ordersExpecting(orderSubtotal: 8182, expectedStatus: LocalOrderStatus::PARTIALLY_REFUNDED),
            $this->refundsSumming(4000),
        );

        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    public function test_second_partial_pushing_to_the_full_subtotal_marks_refunded(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            $this->ordersExpecting(orderSubtotal: 8182, expectedStatus: LocalOrderStatus::REFUNDED),
            $this->refundsSumming(8182),
        );

        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    public function test_canceling_a_previously_completed_refund_reverts_to_paid(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            $this->ordersExpecting(orderSubtotal: 8182, expectedStatus: LocalOrderStatus::PAID),
            $this->refundsSumming(0),
        );

        $reaction->handle($this->event(RefundCanceled::class, 'canceled'));
    }

    public function test_unknown_order_subtotal_stays_conservative_as_partially_refunded(): void
    {
        $reaction = new SyncOrderOnRefundChange(
            $this->ordersExpecting(orderSubtotal: null, expectedStatus: LocalOrderStatus::PARTIALLY_REFUNDED),
            $this->refundsSumming(8182),
        );

        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    public function test_it_does_nothing_when_the_order_is_not_tracked_locally(): void
    {
        $orders = Mockery::mock(OrderRepositoryInterface::class);
        $orders->shouldReceive('findByVatlyId')->with('ord_original_1')->once()->andReturnNull();
        $orders->shouldNotReceive('update');

        $refunds = Mockery::mock(RefundReader::class);
        $refunds->shouldNotReceive('sumSubtotalsForOrder');

        $reaction = new SyncOrderOnRefundChange($orders, $refunds);
        $reaction->handle($this->event(RefundCompleted::class, 'refunded'));
    }

    private function ordersExpecting(?int $orderSubtotal, string $expectedStatus): OrderRepositoryInterface
    {
        $order = Mockery::mock(OrderInterface::class);
        $order->shouldReceive('getSubtotal')->andReturn($orderSubtotal);

        $orders = Mockery::mock(OrderRepositoryInterface::class);
        $orders->shouldReceive('findByVatlyId')->with('ord_original_1')->once()->andReturn($order);
        $orders->shouldReceive('update')->once()->with($order, Mockery::on(
            fn (UpdateOrderData $data) => $data->status === $expectedStatus,
        ))->andReturn($order);

        return $orders;
    }

    private function refundsSumming(int $cumulative): RefundReader
    {
        $refunds = Mockery::mock(RefundReader::class);
        $refunds->shouldReceive('sumSubtotalsForOrder')->with('ord_original_1')->once()->andReturn($cumulative);

        return $refunds;
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
