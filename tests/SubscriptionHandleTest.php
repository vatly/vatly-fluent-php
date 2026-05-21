<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use DateTimeImmutable;
use Mockery;
use ReflectionClass;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Link;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Exceptions\FeatureUnavailableException;
use Vatly\Fluent\SubscriptionHandle;

class SubscriptionHandleTest extends TestCase
{
    public function test_state_accessors_delegate_to_the_wrapped_subscription(): void
    {
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getType')->andReturn('default');
        $subscription->shouldReceive('getPlanId')->andReturn('plan_basic');
        $subscription->shouldReceive('getName')->andReturn('Basic Plan');
        $subscription->shouldReceive('getQuantity')->andReturn(1);
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('isCancelled')->andReturn(false);
        $subscription->shouldReceive('isOnGracePeriod')->andReturn(false);

        $handle = $this->buildHandle(subscription: $subscription);

        $this->assertSame('subscription_abc', $handle->getVatlyId());
        $this->assertSame('default', $handle->getType());
        $this->assertSame('plan_basic', $handle->getPlanId());
        $this->assertSame('Basic Plan', $handle->getName());
        $this->assertSame(1, $handle->getQuantity());
        $this->assertTrue($handle->active());
        $this->assertFalse($handle->cancelled());
        $this->assertFalse($handle->onGracePeriod());
    }

    public function test_swap_calls_the_action_and_persists_the_change(): void
    {
        $subscription = $this->stubSubscription('subscription_abc');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_premium',
            'quantity' => 2,
        ]);

        $swapAction = Mockery::mock(SwapSubscriptionPlan::class);
        $swapAction->shouldReceive('execute')
            ->with('subscription_abc', 'plan_premium', [])
            ->andReturn($apiResponse);

        $updatedSubscription = Mockery::mock(SubscriptionInterface::class);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->planId === 'plan_premium'
                    && $data->quantity === 2;
            }))
            ->andReturn($updatedSubscription);

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            swapAction: $swapAction,
        );

        $returned = $handle->swap('plan_premium');

        $this->assertSame($handle, $returned);
        $this->assertSame($updatedSubscription, $handle->model());
    }

    public function test_swap_and_invoice_forces_immediate_application(): void
    {
        $subscription = $this->stubSubscription('subscription_abc');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_premium',
            'quantity' => 1,
        ]);

        $swapAction = Mockery::mock(SwapSubscriptionPlan::class);
        $swapAction->shouldReceive('execute')
            ->with('subscription_abc', 'plan_premium', [
                'applyImmediately' => true,
                'invoiceImmediately' => true,
            ])
            ->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            swapAction: $swapAction,
        );

        $this->assertSame($handle, $handle->swapAndInvoice('plan_premium'));
    }

    public function test_cancel_calls_the_api_only(): void
    {
        $subscription = $this->stubSubscription('subscription_abc');

        $cancelAction = Mockery::mock(CancelSubscription::class);
        $cancelAction->shouldReceive('execute')->once()->with('subscription_abc');

        $handle = $this->buildHandle(
            subscription: $subscription,
            cancelAction: $cancelAction,
        );

        $handle->cancel();
    }

    public function test_create_billing_update_link_returns_the_href(): void
    {
        $subscription = $this->stubSubscription('subscription_abc');

        $link = new Link('https://vatly.example/billing/upd_xyz', 'text/html');

        $linkAction = Mockery::mock(UpdateSubscriptionBilling::class);
        $linkAction->shouldReceive('execute')
            ->with('subscription_abc', ['redirectUrlSuccess' => 'https://app/done'])
            ->andReturn($link);

        $handle = $this->buildHandle(
            subscription: $subscription,
            updateBillingAction: $linkAction,
        );

        $this->assertSame(
            'https://vatly.example/billing/upd_xyz',
            $handle->updateBilling(['redirectUrlSuccess' => 'https://app/done']),
        );
    }

    public function test_sync_fetches_from_api_and_persists(): void
    {
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getEndsAt')->andReturn(null);

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => '2026-06-01T00:00:00+00:00',
            'cancelledAt' => null,
            'trialUntil' => null,
        ]);

        $getAction = Mockery::mock(GetSubscription::class);
        $getAction->shouldReceive('execute')
            ->with('subscription_abc')
            ->andReturn($apiResponse);

        $updated = Mockery::mock(SubscriptionInterface::class);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->planId === 'plan_basic'
                    && $data->name === 'Basic'
                    && $data->quantity === 1
                    && $data->endsAt instanceof DateTimeImmutable;
            }))
            ->andReturn($updated);

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            getSubscriptionAction: $getAction,
        );

        $handle->sync();

        $this->assertSame($updated, $handle->model());
    }

    public function test_resume_throws_feature_unavailable(): void
    {
        $handle = $this->buildHandle();

        $this->expectException(FeatureUnavailableException::class);

        $handle->resume();
    }

    private function stubSubscription(string $vatlyId): SubscriptionInterface
    {
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn($vatlyId);

        return $subscription;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeApiSubscription(array $fields): ApiSubscription
    {
        /** @var ApiSubscription $resource */
        $resource = (new ReflectionClass(ApiSubscription::class))->newInstanceWithoutConstructor();

        foreach ($fields as $key => $value) {
            $resource->{$key} = $value;
        }

        return $resource;
    }

    private function buildHandle(
        ?SubscriptionInterface $subscription = null,
        ?SubscriptionRepositoryInterface $subscriptions = null,
        ?SwapSubscriptionPlan $swapAction = null,
        ?CancelSubscription $cancelAction = null,
        ?GetSubscription $getSubscriptionAction = null,
        ?UpdateSubscriptionBilling $updateBillingAction = null,
    ): SubscriptionHandle {
        return new SubscriptionHandle(
            subscription: $subscription ?? Mockery::mock(SubscriptionInterface::class),
            subscriptions: $subscriptions ?? Mockery::mock(SubscriptionRepositoryInterface::class),
            swapAction: $swapAction ?? Mockery::mock(SwapSubscriptionPlan::class),
            cancelAction: $cancelAction ?? Mockery::mock(CancelSubscription::class),
            getSubscriptionAction: $getSubscriptionAction ?? Mockery::mock(GetSubscription::class),
            updateBillingAction: $updateBillingAction
                ?? Mockery::mock(UpdateSubscriptionBilling::class),
        );
    }
}
