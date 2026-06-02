<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Testing;

use Mockery;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Testing\FakeCheckout;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Tests\TestCase;

class FakeVatlyTest extends TestCase
{
    private function profile(string $id = 'cus_1'): CustomerProfile
    {
        return new CustomerProfile(vatlyId: $id);
    }

    public function test_subscription_create_returns_scripted_checkout_and_records_the_plan(): void
    {
        $fake = (new FakeVatly())->onSubscriptionCreate(
            fn (string $planId) => FakeCheckout::make('https://checkout.vatly.test/chk_1'),
        );

        $checkout = $fake->subscriptionBuilder($this->profile())
            ->toPlan('plan_pro')
            ->withQuantity(2)
            ->create();

        $this->assertSame('https://checkout.vatly.test/chk_1', $checkout->links->checkoutUrl->href);
        $fake->assertSubscriptionCreated('plan_pro');
        $fake->assertNothingCanceled();
    }

    public function test_subscription_create_falls_back_to_the_default_checkout(): void
    {
        $fake = (new FakeVatly())->withDefaultCheckout(FakeCheckout::make('https://checkout.vatly.test/default'));

        $checkout = $fake->subscriptionBuilder($this->profile())->toPlan('plan_basic')->create();

        $this->assertSame('https://checkout.vatly.test/default', $checkout->links->checkoutUrl->href);
    }

    public function test_checkout_builder_records_the_product(): void
    {
        $fake = new FakeVatly();

        $fake->checkoutBuilder($this->profile())->create(
            items: [['id' => 'product_xyz', 'quantity' => 1]],
            redirectUrlSuccess: 'https://app/success',
            redirectUrlCanceled: 'https://app/cancel',
        );

        $fake->assertCheckoutCreated(productId: 'product_xyz');
    }

    public function test_swap_and_cancel_are_recorded(): void
    {
        $fake = new FakeVatly();

        $subscription = Mockery::mock(SubscriptionInterface::class);
        $subscription->shouldReceive('getPlanId')->andReturn('plan_starter');
        $subscription->shouldReceive('getVatlyId')->andReturn('subscription_42');

        $handle = $fake->subscription($subscription);
        $handle->swap('plan_pro');

        $fake->assertSubscriptionSwapped(from: 'plan_starter', to: 'plan_pro');
        $fake->assertNothingCanceled();

        $handle->cancel();
        $fake->assertSubscriptionCanceled('subscription_42');
    }

    public function test_assert_nothing_created_passes_on_a_fresh_fake(): void
    {
        (new FakeVatly())->assertNothingCreated();
    }

    public function test_it_is_a_drop_in_for_the_real_vatly(): void
    {
        $this->assertInstanceOf(\Vatly\Fluent\Vatly::class, new FakeVatly());
    }
}
