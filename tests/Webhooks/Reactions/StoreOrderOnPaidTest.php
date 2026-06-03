<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\API\Types\Money;
use Vatly\API\Types\OrderLineData;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionStarted;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderWasCreatedFromWebhook;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;

class StoreOrderOnPaidTest extends TestCase
{
    public function test_it_supports_order_paid_events(): void
    {
        $reaction = new StoreOrderOnPaid(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
            Mockery::mock(EventDispatcherInterface::class),
        );

        $this->assertTrue($reaction->supports($this->makeEvent()));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new StoreOrderOnPaid(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
            Mockery::mock(EventDispatcherInterface::class),
        );

        $event = new SubscriptionStarted('cus_1', 'sub_1', 'plan_1', 'default', 'Monthly', 1);

        $this->assertFalse($reaction->supports($event));
    }

    public function test_it_stores_an_order_with_host_customer_id_from_bindings_when_none_exists(): void
    {
        $taxSummary = $this->makeTaxSummary();
        $event = $this->makeEvent(taxSummary: $taxSummary);

        $order = Mockery::mock(OrderInterface::class);
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
                && $data->paymentMethod === 'card'
                && $data->hostCustomerId === 'host_7';
        }))->andReturn($order);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_1')->once()->andReturn('host_7');
        $bindings->shouldReceive('record')->with('cus_1')->once();

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) use ($order) {
            return $event instanceof OrderWasCreatedFromWebhook
                && $event->order === $order;
        }));

        $reaction = new StoreOrderOnPaid($repo, $bindings, $dispatcher);
        $reaction->handle($event);
    }

    public function test_it_updates_an_existing_order_without_consulting_bindings_or_dispatching(): void
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

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('hostCustomerIdFor');
        $bindings->shouldNotReceive('record');

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        $reaction = new StoreOrderOnPaid($repo, $bindings, $dispatcher);
        $reaction->handle($event);
    }

    public function test_it_does_not_dispatch_order_created_when_store_returns_null(): void
    {
        $event = $this->makeEvent();

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->andReturnNull();

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_1')->once()->andReturnNull();
        $bindings->shouldReceive('record')->with('cus_1')->once();

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        $reaction = new StoreOrderOnPaid($repo, $bindings, $dispatcher);
        $reaction->handle($event);
    }

    public function test_it_skips_bindings_when_customer_id_is_empty(): void
    {
        $event = new OrderPaid(
            customerId: '',
            orderId: 'ord_anon',
            status: 'paid',
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: new TaxSummaryCollection([]),
            invoiceNumber: 'INV-001',
            paymentMethod: 'card',
        );

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_anon')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(
            fn (StoreOrderData $data) => $data->customerId === ''
                && $data->hostCustomerId === null
                && $data->status === 'paid',
        ))->andReturn(Mockery::mock(OrderInterface::class));

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('hostCustomerIdFor');
        $bindings->shouldNotReceive('record');

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->once();

        (new StoreOrderOnPaid($repo, $bindings, $dispatcher))->handle($event);
    }

    public function test_it_plumbs_metadata_through_store_and_update_paths(): void
    {
        $metadata = ['fluentcart_transaction_id' => 'tx_42'];
        $event = new OrderPaid(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: 'paid',
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: new TaxSummaryCollection([]),
            invoiceNumber: 'INV-001',
            paymentMethod: 'card',
            metadata: $metadata,
        );

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(
            fn (StoreOrderData $data) => $data->metadata === $metadata,
        ))->andReturn(Mockery::mock(OrderInterface::class));

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->andReturn(null);
        $bindings->shouldReceive('record')->once();

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->once();

        (new StoreOrderOnPaid($repo, $bindings, $dispatcher))->handle($event);

        $existing = Mockery::mock(OrderInterface::class);
        $updateRepo = Mockery::mock(OrderRepositoryInterface::class);
        $updateRepo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $updateRepo->shouldReceive('update')->once()->with($existing, Mockery::on(
            fn (UpdateOrderData $data) => $data->metadata === $metadata,
        ))->andReturn($existing);

        $updateDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $updateDispatcher->shouldNotReceive('dispatch');

        (new StoreOrderOnPaid($updateRepo, Mockery::mock(CustomerBindingRepository::class), $updateDispatcher))->handle($event);
    }

    public function test_it_forwards_order_lines_into_store_order_data(): void
    {
        $lines = [
            new OrderLineData(
                vatlyId: 'order_item_sub',
                description: 'Pro plan',
                quantity: 1,
                basePrice: self::money(2000),
                total: self::money(2420),
                subtotal: self::money(2000),
                taxSummary: new TaxSummaryCollection([]),
                productType: 'subscription',
                productId: 'subscription_abc',
            ),
        ];

        $event = new OrderPaid(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: 'paid',
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: new TaxSummaryCollection([]),
            invoiceNumber: 'INV-001',
            paymentMethod: 'card',
            lines: $lines,
        );

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(
            fn (StoreOrderData $data) => $data->lines === $lines,
        ))->andReturn(Mockery::mock(OrderInterface::class));

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->andReturn(null);
        $bindings->shouldReceive('record')->once();

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->once();

        (new StoreOrderOnPaid($repo, $bindings, $dispatcher))->handle($event);
    }

    public function test_it_does_not_re_write_lines_for_an_already_persisted_order(): void
    {
        $event = $this->makeEvent();

        $existing = Mockery::mock(OrderInterface::class);
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->andReturn($existing);
        $repo->shouldNotReceive('store');

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        (new StoreOrderOnPaid($repo, Mockery::mock(CustomerBindingRepository::class), $dispatcher))->handle($event);
    }

    private function makeEvent(?TaxSummaryCollection $taxSummary = null): OrderPaid
    {
        return new OrderPaid(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: 'paid',
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: $taxSummary ?? new TaxSummaryCollection([]),
            invoiceNumber: 'INV-001',
            paymentMethod: 'card',
        );
    }

    private function makeTaxSummary(): TaxSummaryCollection
    {
        return new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '17.18'],
            ],
        ]);
    }
}
