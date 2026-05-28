<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\CustomerService;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Exceptions\IncompleteWiring;
use Vatly\Fluent\Order;
use Vatly\Fluent\Subscription;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Webhooks\WebhookProcessor;
use Vatly\Fluent\Wiring;

class VatlyTest extends TestCase
{
    // --- API client construction ---

    public function test_api_client_uses_configuration_values(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration([
                'api_key' => 'test_abcdefghijklmnopqrstuvwxyz',
                'api_url' => 'https://api.example.test',
                'api_version' => 'v2',
            ]),
        ));

        $this->assertSame('https://api.example.test', $vatly->getApiClient()->getApiEndpoint());
        $this->assertSame('v2', $vatly->getApiClient()->getApiVersion());
    }

    public function test_api_only_factory_constructs_without_repositories(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        // Action accessors resolve fine — they only need the API client.
        $this->assertInstanceOf(CreateCustomer::class, $vatly->createCustomer());
    }

    // --- Lazy caching ---

    public function test_action_accessors_return_the_same_instance(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        $this->assertSame($vatly->createCustomer(), $vatly->createCustomer());
        $this->assertSame($vatly->getOrder(), $vatly->getOrder());
    }

    public function test_customers_helper_is_cached(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertSame($vatly->customers(), $vatly->customers());
    }

    // --- IncompleteWiring on missing dependencies ---

    public function test_customers_throws_when_bindings_missing(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'CustomerService'.*'customerBindings'/");

        $vatly->customers();
    }

    public function test_webhook_processor_throws_when_events_dispatcher_missing(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
        ));

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'WebhookProcessor'.*'events'/");

        $vatly->webhookProcessor();
    }

    public function test_webhook_processor_throws_when_webhook_calls_missing(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            events: Mockery::mock(EventDispatcherInterface::class),
        ));

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'webhookCalls'/");

        $vatly->webhookProcessor();
    }

    public function test_webhook_processor_throws_when_customer_bindings_missing(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            events: Mockery::mock(EventDispatcherInterface::class),
        ));

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'WebhookProcessor'.*'customerBindings'/");

        $vatly->webhookProcessor();
    }

    // --- Happy-path resolution ---

    public function test_customers_returns_helper_from_wiring(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertInstanceOf(CustomerService::class, $vatly->customers());
    }

    public function test_checkout_builder_is_constructed_per_call(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');
        $profile = new CustomerProfile(vatlyId: 'cus_abc');

        $first = $vatly->checkoutBuilder($profile);
        $second = $vatly->checkoutBuilder($profile);

        $this->assertInstanceOf(CheckoutBuilder::class, $first);
        $this->assertNotSame($first, $second);
    }

    public function test_subscription_builder_is_constructed_per_call(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');
        $profile = new CustomerProfile(vatlyId: 'cus_abc');

        $first = $vatly->subscriptionBuilder($profile);
        $second = $vatly->subscriptionBuilder($profile);

        $this->assertInstanceOf(SubscriptionBuilder::class, $first);
        $this->assertNotSame($first, $second);
    }

    public function test_webhook_processor_is_built_from_wiring(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertInstanceOf(WebhookProcessor::class, $vatly->webhookProcessor());
    }

    public function test_additional_webhook_reactions_from_wiring_are_passed_through(): void
    {
        $customReaction = Mockery::mock(\Vatly\Fluent\Contracts\WebhookReactionInterface::class);

        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            events: Mockery::mock(EventDispatcherInterface::class),
            customerBindings: Mockery::mock(CustomerBindingRepository::class),
            additionalWebhookReactions: [$customReaction],
        ));

        $processor = $vatly->webhookProcessor();

        // Reach in to confirm the custom reaction made it into the processor's list.
        $ref = (new \ReflectionClass($processor))->getProperty('reactions');
        $reactions = $ref->getValue($processor);

        $this->assertContains($customReaction, $reactions);
        // The 3 standard reactions stay on top, plus our extra one = 4.
        $this->assertCount(4, $reactions);
    }

    public function test_subscription_handle_wraps_the_given_subscription(): void
    {
        $vatly = $this->fullyWiredVatly();
        $subscription = Mockery::mock(SubscriptionInterface::class);

        $handle = $vatly->subscription($subscription);

        $this->assertInstanceOf(Subscription::class, $handle);
        $this->assertSame($subscription, $handle->model());
    }

    public function test_subscription_handle_throws_when_subscriptions_missing(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'Subscription'.*'subscriptions'/");

        $vatly->subscription(Mockery::mock(SubscriptionInterface::class));
    }

    public function test_order_handle_wraps_the_given_order(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');
        $order = Mockery::mock(OrderInterface::class);

        $handle = $vatly->order($order);

        $this->assertInstanceOf(Order::class, $handle);
        $this->assertSame($order, $handle->model());
    }

    private function fullyWiredVatly(): Vatly
    {
        return new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            events: Mockery::mock(EventDispatcherInterface::class),
            customerBindings: Mockery::mock(CustomerBindingRepository::class),
        ));
    }
}
