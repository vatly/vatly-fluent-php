<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use Mockery;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionResumed;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\ResumeSubscriptionOnResumed;

class ResumeSubscriptionOnResumedTest extends TestCase
{
    public function test_it_supports_subscription_resumed_events(): void
    {
        $reaction = new ResumeSubscriptionOnResumed(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertTrue($reaction->supports(new SubscriptionResumed('cus_1', 'sub_1')));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new ResumeSubscriptionOnResumed(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', 9900, 8182, new TaxSummaryCollection([]), 'EUR', null, null)));
    }

    public function test_it_clears_the_stored_end_date_for_an_existing_subscription(): void
    {
        $existing = Mockery::mock(SubscriptionInterface::class);

        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) {
            return $data->clearEndsAt === true && $data->endsAt === null;
        }))->andReturn($existing);

        $reaction = new ResumeSubscriptionOnResumed($repo);
        $reaction->handle(new SubscriptionResumed('cus_1', 'sub_1'));
    }

    public function test_it_does_nothing_if_subscription_not_found(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new ResumeSubscriptionOnResumed($repo);
        $reaction->handle(new SubscriptionResumed('cus_1', 'sub_1'));
    }
}
