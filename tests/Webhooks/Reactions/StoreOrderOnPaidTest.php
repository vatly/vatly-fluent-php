<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Types\TaxSummary;
use Vatly\Fluent\Types\TaxSummaryItem;
use Vatly\Fluent\Types\TaxSummaryRate;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;

class StoreOrderOnPaidTest extends TestCase
{
    public function test_it_supports_order_paid_events(): void
    {
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $reaction = new StoreOrderOnPaid($repo);

        $this->assertTrue($reaction->supports($this->makeEvent()));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $reaction = new StoreOrderOnPaid($repo);

        $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

        $this->assertFalse($reaction->supports($event));
    }

    public function test_it_stores_an_order_with_tax_breakdown_when_none_exists(): void
    {
        $taxSummary = $this->makeTaxSummary();
        $event = $this->makeEvent(taxSummary: $taxSummary);

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(function (StoreOrderData $data) use ($taxSummary) {
            return $data->vatlyId === 'ord_1'
                && $data->customerId === 'cus_1'
                && $data->status === 'paid'
                && $data->total === 9900
                && $data->subtotal === 8182
                && $data->taxSummary === $taxSummary
                && $data->currency === 'EUR'
                && $data->invoiceNumber === 'INV-001'
                && $data->paymentMethod === 'card';
        }))->andReturn(Mockery::mock(OrderInterface::class));

        $reaction = new StoreOrderOnPaid($repo);
        $reaction->handle($event);
    }

    public function test_it_updates_an_existing_order_with_tax_breakdown(): void
    {
        $taxSummary = $this->makeTaxSummary();
        $event = $this->makeEvent(taxSummary: $taxSummary);

        $existing = Mockery::mock(OrderInterface::class);
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateOrderData $data) use ($taxSummary) {
            return $data->status === 'paid'
                && $data->total === 9900
                && $data->subtotal === 8182
                && $data->taxSummary === $taxSummary;
        }))->andReturn($existing);
        $repo->shouldNotReceive('store');

        $reaction = new StoreOrderOnPaid($repo);
        $reaction->handle($event);
    }

    private function makeEvent(?TaxSummary $taxSummary = null): OrderPaid
    {
        return new OrderPaid(
            customerId: 'cus_1',
            orderId: 'ord_1',
            total: 9900,
            subtotal: 8182,
            taxSummary: $taxSummary ?? TaxSummary::empty(),
            currency: 'EUR',
            invoiceNumber: 'INV-001',
            paymentMethod: 'card',
        );
    }

    private function makeTaxSummary(): TaxSummary
    {
        return new TaxSummary([
            new TaxSummaryItem(
                rate: new TaxSummaryRate('VAT', 21.0, 100.0),
                amount: 1718,
                currency: 'EUR',
            ),
        ]);
    }
}
