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
use Vatly\Fluent\Actions\ResumeSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;
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

    public function test_swap_forwards_a_price_override_to_the_action(): void
    {
        // api-php's subscriptions->update() accepts a `price` object that, when
        // combined with a plan switch, overrides the new plan's default price.
        // The handle passes swap options straight through, so no dedicated
        // surface is needed — this locks that passthrough.
        $subscription = $this->stubSubscription('subscription_abc');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_premium',
            'quantity' => 1,
        ]);

        $swapAction = Mockery::mock(SwapSubscriptionPlan::class);
        $swapAction->shouldReceive('execute')
            ->once()
            ->with('subscription_abc', 'plan_premium', [
                'price' => ['value' => '99.99', 'currency' => 'EUR'],
                'applyImmediately' => true,
            ])
            ->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            swapAction: $swapAction,
        );

        $handle->swap('plan_premium', [
            'price' => ['value' => '99.99', 'currency' => 'EUR'],
            'applyImmediately' => true,
        ]);
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
        $subscription->shouldReceive('getMandateMethod')->andReturn(null);

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => '2026-06-01T00:00:00+00:00',
            'canceledAt' => null,
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

    public function test_sync_clears_mandate_when_local_had_one_and_api_returns_null(): void
    {
        // Real removal: previously-bound mandate has been removed at Vatly.
        // Local copy must be cleared so portals stop showing a card that
        // no longer exists.
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getEndsAt')->andReturn(null);
        $subscription->shouldReceive('getMandateMethod')->andReturn('card');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => null,
            'canceledAt' => null,
            'mandate' => null,
        ]);

        $getAction = Mockery::mock(GetSubscription::class);
        $getAction->shouldReceive('execute')->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->mandate === null
                    && $data->clearMandate === true;
            }))
            ->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            getSubscriptionAction: $getAction,
        );

        $handle->sync();
    }

    public function test_sync_does_not_clear_mandate_when_local_already_null_and_api_returns_null(): void
    {
        // Transient post-subscription state: API briefly returns mandate:null
        // for a freshly-subscribed customer who hasn't completed payment yet.
        // Conservative: only clear when we observably had something to clear.
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getEndsAt')->andReturn(null);
        $subscription->shouldReceive('getMandateMethod')->andReturn(null);

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => null,
            'canceledAt' => null,
            'mandate' => null,
        ]);

        $getAction = Mockery::mock(GetSubscription::class);
        $getAction->shouldReceive('execute')->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->mandate === null
                    && $data->clearMandate === false;
            }))
            ->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            getSubscriptionAction: $getAction,
        );

        $handle->sync();
    }

    public function test_sync_replaces_mandate_when_api_returns_a_new_one(): void
    {
        // Customer changed their card via hosted billing-update flow; sync
        // should overwrite the local copy with the fresh mandate.
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getEndsAt')->andReturn(null);
        $subscription->shouldReceive('getMandateMethod')->andReturn('card');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => null,
            'canceledAt' => null,
            'mandate' => new \Vatly\API\Types\Mandate('sepa_debit', 'NL91****4300'),
        ]);

        $getAction = Mockery::mock(GetSubscription::class);
        $getAction->shouldReceive('execute')->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->mandate instanceof \Vatly\API\Types\Mandate
                    && $data->mandate->method === 'sepa_debit'
                    && $data->mandate->maskedIdentifier === 'NL91****4300'
                    && $data->clearMandate === false;
            }))
            ->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            getSubscriptionAction: $getAction,
        );

        $handle->sync();
    }

    public function test_sync_atomically_replaces_card_with_paypal_clearing_stale_last4(): void
    {
        // Regression: card → paypal switch. PayPal mandates legitimately have
        // no maskedIdentifier. With the old two-field DTO, null identifier
        // was interpreted as "no change", leaving the old card last4 stored
        // alongside method=paypal — mixed local state like "paypal / 4242".
        // The Mandate object is now atomic so both parts swap together.
        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_abc');
        $subscription->shouldReceive('getEndsAt')->andReturn(null);
        $subscription->shouldReceive('getMandateMethod')->andReturn('card');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'endedAt' => null,
            'canceledAt' => null,
            'mandate' => new \Vatly\API\Types\Mandate('paypal', null),
        ]);

        $getAction = Mockery::mock(GetSubscription::class);
        $getAction->shouldReceive('execute')->andReturn($apiResponse);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->mandate instanceof \Vatly\API\Types\Mandate
                    && $data->mandate->method === 'paypal'
                    && $data->mandate->maskedIdentifier === null
                    && $data->clearMandate === false;
            }))
            ->andReturn(Mockery::mock(SubscriptionInterface::class));

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            getSubscriptionAction: $getAction,
        );

        $handle->sync();
    }

    public function test_resume_calls_the_action_and_clears_ends_at(): void
    {
        $subscription = $this->stubSubscription('subscription_abc');

        $apiResponse = $this->makeApiSubscription([
            'subscriptionPlanId' => 'plan_basic',
            'quantity' => 1,
        ]);

        $resumeAction = Mockery::mock(ResumeSubscription::class);
        $resumeAction->shouldReceive('execute')
            ->once()
            ->with('subscription_abc')
            ->andReturn($apiResponse);

        $updatedSubscription = Mockery::mock(SubscriptionInterface::class);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with($subscription, Mockery::on(function (UpdateSubscriptionData $data) {
                return $data->planId === 'plan_basic'
                    && $data->quantity === 1
                    && $data->endsAt === null
                    && $data->clearEndsAt === true;
            }))
            ->andReturn($updatedSubscription);

        $handle = $this->buildHandle(
            subscription: $subscription,
            subscriptions: $subscriptions,
            resumeAction: $resumeAction,
        );

        $returned = $handle->resume();

        $this->assertSame($handle, $returned);
        $this->assertSame($updatedSubscription, $handle->model());
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
        ?ResumeSubscription $resumeAction = null,
        ?GetSubscription $getSubscriptionAction = null,
        ?UpdateSubscriptionBilling $updateBillingAction = null,
    ): SubscriptionHandle {
        return new SubscriptionHandle(
            subscription: $subscription ?? Mockery::mock(SubscriptionInterface::class),
            subscriptions: $subscriptions ?? Mockery::mock(SubscriptionRepositoryInterface::class),
            swapAction: $swapAction ?? Mockery::mock(SwapSubscriptionPlan::class),
            cancelAction: $cancelAction ?? Mockery::mock(CancelSubscription::class),
            resumeAction: $resumeAction ?? Mockery::mock(ResumeSubscription::class),
            getSubscriptionAction: $getSubscriptionAction ?? Mockery::mock(GetSubscription::class),
            updateBillingAction: $updateBillingAction
                ?? Mockery::mock(UpdateSubscriptionBilling::class),
        );
    }
}
