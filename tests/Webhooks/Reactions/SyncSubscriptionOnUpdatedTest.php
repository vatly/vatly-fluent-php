<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionUpdated;
use Vatly\API\Webhooks\Events\SubscriptionUpdateScheduled;
use Vatly\API\Types\ScheduledSubscriptionUpdate;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnUpdated;

class SyncSubscriptionOnUpdatedTest extends TestCase
{
    public function test_it_supports_subscription_updated_events(): void
    {
        $reaction = new SyncSubscriptionOnUpdated(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertTrue($reaction->supports($this->event()));
    }

    public function test_it_does_not_support_the_scheduled_variant(): void
    {
        // subscription.update_scheduled has not taken effect yet — it is
        // dispatched-only and must NOT be persisted by this reaction.
        $reaction = new SyncSubscriptionOnUpdated(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $scheduled = new SubscriptionUpdateScheduled(
            'cus_1',
            'sub_1',
            true,
            new ScheduledSubscriptionUpdate('plan_2', 'Pro Annual', 'desc', self::money(9900), 3, 'year', 1),
        );

        $this->assertFalse($reaction->supports($scheduled));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new SyncSubscriptionOnUpdated(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertFalse($reaction->supports(
            new OrderPaid('cus_1', 'ord_1', 'paid', self::money(9900), self::money(8182), new TaxSummaryCollection([]), null, null, true),
        ));
    }

    public function test_it_syncs_plan_name_and_quantity_for_an_existing_subscription(): void
    {
        $existing = Mockery::mock(SubscriptionInterface::class);

        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) {
            return $data->planId === 'plan_2'
                && $data->name === 'Pro Annual'
                && $data->quantity === 5
                && $data->mandate === null
                && $data->clearMandate === false;
        }))->andReturn($existing);

        $reaction = new SyncSubscriptionOnUpdated($repo);
        $reaction->handle($this->event(planId: 'plan_2', name: 'Pro Annual', quantity: 5));
    }

    public function test_it_does_nothing_if_subscription_not_found(): void
    {
        // Find-or-skip: an immediate update for a subscription we never recorded
        // must not fabricate a half-known local row.
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new SyncSubscriptionOnUpdated($repo);
        $reaction->handle($this->event());
    }

    private function event(string $planId = 'plan_1', string $name = 'Monthly', int $quantity = 1): SubscriptionUpdated
    {
        return new SubscriptionUpdated(
            'cus_1',
            'sub_1',
            $planId,
            $name,
            'A recurring plan',
            self::money(9900),
            $quantity,
            'month',
            1,
            true,
        );
    }
}
