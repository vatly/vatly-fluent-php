<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\API\Types\Mandate;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionBillingUpdated;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnBillingUpdated;

class SyncSubscriptionOnBillingUpdatedTest extends TestCase
{
    public function test_it_supports_subscription_billing_updated_events(): void
    {
        $reaction = new SyncSubscriptionOnBillingUpdated(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $event = new SubscriptionBillingUpdated('cus_1', 'sub_1', 'plan_1', 'Monthly', 1, new Mandate('card', '4242'));

        $this->assertTrue($reaction->supports($event));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new SyncSubscriptionOnBillingUpdated(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', self::money(9900), self::money(8182), new TaxSummaryCollection([]), null, null)));
    }

    public function test_it_updates_the_stored_mandate_for_an_existing_subscription(): void
    {
        $mandate = new Mandate('sepa_debit', 'NL91****4300');
        $existing = Mockery::mock(SubscriptionInterface::class);

        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) use ($mandate) {
            return $data->planId === 'plan_2'
                && $data->name === 'Annual'
                && $data->quantity === 3
                && $data->mandate === $mandate
                && $data->clearMandate === false;
        }))->andReturn($existing);

        $reaction = new SyncSubscriptionOnBillingUpdated($repo);
        $reaction->handle(new SubscriptionBillingUpdated('cus_1', 'sub_1', 'plan_2', 'Annual', 3, $mandate));
    }

    public function test_a_null_mandate_leaves_the_stored_mandate_untouched(): void
    {
        // Enrichment fell back to the webhook payload (mandate null). The
        // reaction must NOT clear the stored mandate — a transient API blip
        // shouldn't wipe a good payment method; the next sync() reconciles.
        $existing = Mockery::mock(SubscriptionInterface::class);

        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) {
            return $data->mandate === null && $data->clearMandate === false;
        }))->andReturn($existing);

        $reaction = new SyncSubscriptionOnBillingUpdated($repo);
        $reaction->handle(new SubscriptionBillingUpdated('cus_1', 'sub_1', 'plan_1', 'Monthly', 1, null));
    }

    public function test_it_does_nothing_if_subscription_not_found(): void
    {
        // Find-or-skip: a billing update for a subscription we never recorded
        // must not fabricate a half-known local row.
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new SyncSubscriptionOnBillingUpdated($repo);
        $reaction->handle(new SubscriptionBillingUpdated('cus_1', 'sub_1', 'plan_1', 'Monthly', 1, new Mandate('card', '4242')));
    }
}
