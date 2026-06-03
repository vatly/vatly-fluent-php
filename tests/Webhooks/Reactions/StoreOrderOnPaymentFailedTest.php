<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaymentFailed;

class StoreOrderOnPaymentFailedTest extends TestCase
{
    public function test_it_supports_payment_failed_events(): void
    {
        $reaction = new StoreOrderOnPaymentFailed(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertTrue($reaction->supports($this->makeEvent()));
    }

    public function test_it_does_not_support_order_paid_or_other_events(): void
    {
        $reaction = new StoreOrderOnPaymentFailed(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $orderPaid = new OrderPaid(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: 'paid',
            total: 9900,
            subtotal: 8182,
            taxSummary: new TaxSummaryCollection([]),
            currency: 'EUR',
            invoiceNumber: null,
            paymentMethod: null,
        );

        $this->assertFalse($reaction->supports($orderPaid));
    }

    public function test_it_stores_a_failed_order_with_host_customer_id_from_bindings_when_none_exists(): void
    {
        $taxSummary = $this->makeTaxSummary();
        $event = $this->makeEvent(taxSummary: $taxSummary);

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(function (StoreOrderData $data) use ($taxSummary) {
            return $data->vatlyId === 'ord_1'
                && $data->customerId === 'cus_1'
                && $data->status === 'pending'
                && $data->total === 4900
                && $data->subtotal === 4050
                && $data->taxSummary === $taxSummary
                && $data->currency === 'EUR'
                && $data->invoiceNumber === null
                && $data->paymentMethod === 'sepa_direct_debit'
                && $data->hostCustomerId === 'host_7';
        }))->andReturn(Mockery::mock(OrderInterface::class));

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_1')->once()->andReturn('host_7');
        $bindings->shouldReceive('record')->with('cus_1')->once();

        $reaction = new StoreOrderOnPaymentFailed($repo, $bindings);
        $reaction->handle($event);
    }

    public function test_it_updates_an_existing_order_to_failed_without_consulting_bindings(): void
    {
        $taxSummary = $this->makeTaxSummary();
        $event = $this->makeEvent(taxSummary: $taxSummary);

        $existing = Mockery::mock(OrderInterface::class);
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateOrderData $data) use ($taxSummary) {
            return $data->status === 'pending'
                && $data->total === 4900
                && $data->subtotal === 4050
                && $data->taxSummary === $taxSummary
                && $data->paymentMethod === 'sepa_direct_debit';
        }))->andReturn($existing);
        $repo->shouldNotReceive('store');

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('hostCustomerIdFor');
        $bindings->shouldNotReceive('record');

        $reaction = new StoreOrderOnPaymentFailed($repo, $bindings);
        $reaction->handle($event);
    }

    public function test_it_persists_whatever_status_the_enriched_order_carries(): void
    {
        // Regression: an earlier version of this reaction hardcoded `'failed'`,
        // which diverged from the real Vatly order state ('pending' during
        // dunning, sometimes 'canceled' after the process ends). We mirror,
        // not synthesise.
        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $existing = Mockery::mock(OrderInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(
            fn (UpdateOrderData $data) => $data->status === 'canceled',
        ))->andReturn($existing);

        (new StoreOrderOnPaymentFailed($repo, Mockery::mock(CustomerBindingRepository::class)))
            ->handle($this->makeEvent(status: 'canceled'));
    }

    public function test_it_skips_bindings_when_customer_id_is_empty(): void
    {
        $event = new OrderPaymentFailed(
            customerId: '',
            orderId: 'ord_anon',
            status: 'pending',
            total: 4900,
            subtotal: 4050,
            taxSummary: new TaxSummaryCollection([]),
            currency: 'EUR',
            invoiceNumber: null,
            paymentMethod: 'sepa_direct_debit',
        );

        $repo = Mockery::mock(OrderRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('ord_anon')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(
            fn (StoreOrderData $data) => $data->customerId === ''
                && $data->hostCustomerId === null
                && $data->status === 'pending',
        ))->andReturn(Mockery::mock(OrderInterface::class));

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('hostCustomerIdFor');
        $bindings->shouldNotReceive('record');

        (new StoreOrderOnPaymentFailed($repo, $bindings))->handle($event);
    }

    public function test_it_plumbs_metadata_through_store_and_update_paths(): void
    {
        $metadata = ['fluentcart_transaction_id' => 'tx_42'];
        $event = new OrderPaymentFailed(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: 'pending',
            total: 4900,
            subtotal: 4050,
            taxSummary: new TaxSummaryCollection([]),
            currency: 'EUR',
            invoiceNumber: null,
            paymentMethod: 'sepa_direct_debit',
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

        (new StoreOrderOnPaymentFailed($repo, $bindings))->handle($event);

        $existing = Mockery::mock(OrderInterface::class);
        $updateRepo = Mockery::mock(OrderRepositoryInterface::class);
        $updateRepo->shouldReceive('findByVatlyId')->with('ord_1')->once()->andReturn($existing);
        $updateRepo->shouldReceive('update')->once()->with($existing, Mockery::on(
            fn (UpdateOrderData $data) => $data->metadata === $metadata,
        ))->andReturn($existing);

        (new StoreOrderOnPaymentFailed($updateRepo, Mockery::mock(CustomerBindingRepository::class)))->handle($event);
    }

    private function makeEvent(?TaxSummaryCollection $taxSummary = null, string $status = 'pending'): OrderPaymentFailed
    {
        return new OrderPaymentFailed(
            customerId: 'cus_1',
            orderId: 'ord_1',
            status: $status,
            total: 4900,
            subtotal: 4050,
            taxSummary: $taxSummary ?? new TaxSummaryCollection([]),
            currency: 'EUR',
            invoiceNumber: null,
            paymentMethod: 'sepa_direct_debit',
        );
    }

    private function makeTaxSummary(): TaxSummaryCollection
    {
        return new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '8.50'],
            ],
        ]);
    }
}
