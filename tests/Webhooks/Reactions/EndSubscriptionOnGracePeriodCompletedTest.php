<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks\Reactions;

use DateTimeImmutable;
use Mockery;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\SubscriptionCancellationGracePeriodCompleted;
use Vatly\Fluent\Tests\TestCase;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\Fluent\Webhooks\Reactions\EndSubscriptionOnGracePeriodCompleted;

class EndSubscriptionOnGracePeriodCompletedTest extends TestCase
{
    public function test_it_supports_grace_period_completed_events(): void
    {
        $reaction = new EndSubscriptionOnGracePeriodCompleted(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $event = new SubscriptionCancellationGracePeriodCompleted('cus_1', 'sub_1', new DateTimeImmutable());

        $this->assertTrue($reaction->supports($event));
    }

    public function test_it_does_not_support_other_events(): void
    {
        $reaction = new EndSubscriptionOnGracePeriodCompleted(
            Mockery::mock(SubscriptionRepositoryInterface::class),
        );

        $this->assertFalse($reaction->supports(new OrderPaid('cus_1', 'ord_1', 'paid', self::money(9900), self::money(8182), new TaxSummaryCollection([]), null, null)));
    }

    public function test_it_stamps_the_actual_ends_at_onto_an_existing_subscription(): void
    {
        $endsAt = new DateTimeImmutable('2026-05-20T10:00:00Z');
        $existing = Mockery::mock(SubscriptionInterface::class);

        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturn($existing);
        $repo->shouldReceive('update')->once()->with($existing, Mockery::on(function (UpdateSubscriptionData $data) use ($endsAt) {
            return $data->endsAt === $endsAt && $data->clearEndsAt === false;
        }))->andReturn($existing);

        $reaction = new EndSubscriptionOnGracePeriodCompleted($repo);
        $reaction->handle(new SubscriptionCancellationGracePeriodCompleted('cus_1', 'sub_1', $endsAt));
    }

    public function test_it_does_nothing_if_subscription_not_found(): void
    {
        $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
        $repo->shouldReceive('findByVatlyId')->with('sub_1')->once()->andReturnNull();
        $repo->shouldNotReceive('update');

        $reaction = new EndSubscriptionOnGracePeriodCompleted($repo);
        $reaction->handle(new SubscriptionCancellationGracePeriodCompleted('cus_1', 'sub_1', new DateTimeImmutable()));
    }
}
