<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\ChargebackInterface;
use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Data\StoreChargebackData;
use Vatly\Fluent\Data\UpdateChargebackData;
use Vatly\API\Webhooks\Events\OrderChargebackReceived;
use Vatly\API\Webhooks\Events\OrderChargebackReversed;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange;

class SyncChargebackOnStatusChangeTest extends TestCase
{
    public function test_it_supports_both_chargeback_events(): void
    {
        $reaction = new SyncChargebackOnStatusChange(
            Mockery::mock(ChargebackRepositoryInterface::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertTrue($reaction->supports($this->received('pending')));
        $this->assertTrue($reaction->supports($this->reversed('won')));
        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', self::money(9900), self::money(8182), new TaxSummaryCollection([]), null, null, true)));
    }

    public function test_it_stores_a_new_chargeback_resolving_host_customer_from_bindings(): void
    {
        $stored = Mockery::mock(ChargebackInterface::class);
        $repo = Mockery::mock(ChargebackRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('chargeback_1')->once()->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(function (StoreChargebackData $data) {
            return $data->vatlyId === 'chargeback_1'
                && $data->customerId === 'cus_1'
                && $data->status === 'pending'
                && $data->total === 9900
                && $data->originalOrderId === 'ord_original_1'
                && $data->testmode === true
                && $data->hostCustomerId === 'host_42';
        }))->andReturn($stored);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_1')->once()->andReturn('host_42');
        $bindings->shouldReceive('record')->with('cus_1')->once();

        $reaction = new SyncChargebackOnStatusChange($repo, $bindings);
        $reaction->handle($this->received('pending'));
    }

    public function test_it_updates_an_existing_chargeback_on_reversal(): void
    {
        $existing = Mockery::mock(ChargebackInterface::class);
        $repo = Mockery::mock(ChargebackRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('chargeback_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(
            fn (UpdateChargebackData $data) => $data->status === 'won' && $data->total === 9900,
        ))->andReturn($existing);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('record');

        $reaction = new SyncChargebackOnStatusChange($repo, $bindings);
        $reaction->handle($this->reversed('won'));
    }

    public function test_it_skips_binding_when_customer_id_is_empty(): void
    {
        $stored = Mockery::mock(ChargebackInterface::class);
        $repo = Mockery::mock(ChargebackRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->andReturnNull();
        $repo->shouldReceive('store')->once()->with(Mockery::on(
            fn (StoreChargebackData $data) => $data->hostCustomerId === null,
        ))->andReturn($stored);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('hostCustomerIdFor');
        $bindings->shouldNotReceive('record');

        // Sparse (un-enriched) event: chargebackId set, but no customer id.
        $event = new OrderChargebackReceived('ord_original_1', 'chargeback_1', 'ord_original_1', true, null);

        $reaction = new SyncChargebackOnStatusChange($repo, $bindings);
        $reaction->handle($event);
    }

    private function received(string $status): OrderChargebackReceived
    {
        return new OrderChargebackReceived(
            orderId: 'ord_original_1',
            chargebackId: 'chargeback_1',
            originalOrderId: 'ord_original_1',
            testmode: true,
            reason: 'fraudulent',
            customerId: 'cus_1',
            status: $status,
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: new TaxSummaryCollection([]),
            currency: 'EUR',
        );
    }

    private function reversed(string $status): OrderChargebackReversed
    {
        return new OrderChargebackReversed(
            orderId: 'ord_original_1',
            chargebackId: 'chargeback_1',
            originalOrderId: 'ord_original_1',
            testmode: true,
            reason: 'fraudulent',
            customerId: 'cus_1',
            status: $status,
            total: self::money(9900),
            subtotal: self::money(8182),
            taxSummary: new TaxSummaryCollection([]),
            currency: 'EUR',
        );
    }
}
