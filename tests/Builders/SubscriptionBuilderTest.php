<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Builders;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionBuilderTest extends TestCase
{
    private ConfigurationInterface $config;
    private BillableInterface $owner;
    private CreateCheckout $createCheckout;
    private CheckoutBuilder $checkoutBuilder;
    private SubscriptionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createTestConfig();
        $this->owner = $this->createTestOwner('vat_sub_owner_123');
        $this->createCheckout = $this->createTestCreateCheckout();
        $this->checkoutBuilder = new CheckoutBuilder($this->owner, $this->createCheckout);
        $this->builder = new SubscriptionBuilder($this->config, $this->owner, $this->checkoutBuilder);
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

            public function getBillableModel(): string
            {
                return 'App\\Models\\User';
            }
        };
    }

    private function createTestOwner(string $vatlyId): BillableInterface
    {
        return new class($vatlyId) implements BillableInterface {
            public function __construct(private string $vatlyId) {}

            public function getVatlyId(): string
            {
                return $this->vatlyId;
            }

            public function setVatlyId(string $id): void
            {
                $this->vatlyId = $id;
            }

            public function hasVatlyId(): bool
            {
                return $this->vatlyId !== '';
            }

            public function getVatlyEmail(): ?string
            {
                return 'owner@example.com';
            }

            public function getVatlyName(): ?string
            {
                return 'Test Owner';
            }

            public function getKey(): string|int
            {
                return 1;
            }

            public function save(): mixed
            {
                return true;
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
                $checkout->merchantId = 'merchant_test';
                $checkout->redirectUrlSuccess = 'https://example.com/success';
                $checkout->redirectUrlCanceled = 'https://example.com/canceled';

                return $checkout;
            }
        };
    }
}
