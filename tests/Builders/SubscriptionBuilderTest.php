<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Builders;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionBuilderTest extends TestCase
{
    private ConfigurationInterface $config;
    private CustomerProfile $customer;
    private CreateCheckout $createCheckout;
    private CheckoutBuilder $checkoutBuilder;
    private SubscriptionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createTestConfig();
        $this->customer = new CustomerProfile(vatlyId: 'vat_sub_owner_123');
        $this->createCheckout = $this->createTestCreateCheckout();
        $this->checkoutBuilder = new CheckoutBuilder($this->customer, $this->createCheckout);
        $this->builder = new SubscriptionBuilder($this->config, $this->customer, $this->checkoutBuilder);
    }

    public function test_to_plan_sets_the_plan_id(): void
    {
        $result = $this->builder->toPlan('plan_abc');
        $subscriptionPayload = $this->builder->getSubscriptionPayload();

        $this->assertSame($this->builder, $result);
        $this->assertSame('plan_abc', $subscriptionPayload['id']);
    }

    public function test_with_quantity_sets_the_quantity(): void
    {
        $result = $this->builder->withQuantity(5);
        $subscriptionPayload = $this->builder->getSubscriptionPayload();

        $this->assertSame($this->builder, $result);
        $this->assertSame(5, $subscriptionPayload['quantity']);
    }

    public function test_with_redirect_url_success_returns_builder(): void
    {
        $result = $this->builder
            ->toPlan('plan_123')
            ->withRedirectUrlSuccess('https://custom.test/success');

        $this->assertSame($this->builder, $result);
    }

    public function test_with_redirect_url_canceled_returns_builder(): void
    {
        $result = $this->builder
            ->toPlan('plan_123')
            ->withRedirectUrlCanceled('https://custom.test/canceled');

        $this->assertSame($this->builder, $result);
    }

    public function test_in_testmode_returns_builder(): void
    {
        $result = $this->builder->inTestmode();

        $this->assertSame($this->builder, $result);
    }

    public function test_in_live_mode_returns_builder(): void
    {
        $result = $this->builder->inLiveMode();

        $this->assertSame($this->builder, $result);
    }

    public function test_get_subscription_payload_returns_plan_and_quantity(): void
    {
        $this->builder->toPlan('plan_premium')->withQuantity(3);

        $payload = $this->builder->getSubscriptionPayload();

        $this->assertSame([
            'quantity' => 3,
            'id' => 'plan_premium',
        ], $payload);
    }

    public function test_get_subscription_payload_defaults_to_quantity_of_1(): void
    {
        $this->builder->toPlan('plan_basic');

        $payload = $this->builder->getSubscriptionPayload();

        $this->assertSame(1, $payload['quantity']);
    }

    public function test_with_trial_days_adds_trial_days_to_the_payload(): void
    {
        $result = $this->builder->toPlan('plan_pro')->withTrialDays(14);

        $payload = $this->builder->getSubscriptionPayload();

        $this->assertSame($this->builder, $result);
        $this->assertSame(14, $payload['trialDays']);
        $this->assertSame('plan_pro', $payload['id']);
    }

    public function test_trial_days_is_absent_from_payload_when_not_set(): void
    {
        $this->builder->toPlan('plan_pro');

        $this->assertArrayNotHasKey('trialDays', $this->builder->getSubscriptionPayload());
    }

    public function test_with_trial_days_rejects_non_positive_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->withTrialDays(0);
    }

    public function test_with_trial_ends_at_converts_to_whole_days_rounding_up(): void
    {
        // 13 days + 1 hour from now rounds up to a 14-day trial so it never
        // ends earlier than requested.
        $endsAt = (new \DateTimeImmutable())->modify('+13 days +1 hour');

        $this->builder->toPlan('plan_pro')->withTrialEndsAt($endsAt);

        $this->assertSame(14, $this->builder->getSubscriptionPayload()['trialDays']);
    }

    public function test_with_trial_ends_at_rejects_a_past_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->withTrialEndsAt((new \DateTimeImmutable())->modify('-1 day'));
    }

    public function test_get_checkout_builder_returns_the_checkout_builder_instance(): void
    {
        $checkoutBuilder = $this->builder->getCheckoutBuilder();

        $this->assertInstanceOf(CheckoutBuilder::class, $checkoutBuilder);
        $this->assertSame($this->checkoutBuilder, $checkoutBuilder);
    }

    private function createTestConfig(): ConfigurationInterface
    {
        return new class implements ConfigurationInterface {
            public function getApiKey(): string
            {
                return 'test_api_key';
            }

            public function getWebhookSecret(): string
            {
                return 'test_webhook_secret';
            }

            public function getDefaultRedirectUrlSuccess(): string
            {
                return 'https://default.test/success';
            }

            public function getDefaultRedirectUrlCanceled(): string
            {
                return 'https://default.test/canceled';
            }

            public function isTestmode(): bool
            {
                return true;
            }

            public function getApiUrl(): string
            {
                return 'https://api.vatly.test';
            }

            public function getApiVersion(): string
            {
                return 'v1';
            }
        };
    }

    private function createTestCreateCheckout(): CreateCheckout
    {
        return new class extends CreateCheckout {
            public function __construct() {}

            public function execute(array $payload, array $filters = []): Checkout
            {
                $checkout = new Checkout();
                $checkout->id = 'chk_sub_123';
                $checkout->status = 'created';
                $checkout->testmode = false;
                $checkout->redirectUrlSuccess = 'https://example.com/success';
                $checkout->redirectUrlCanceled = 'https://example.com/canceled';

                return $checkout;
            }
        };
    }
}
