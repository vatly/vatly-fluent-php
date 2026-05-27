<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Billable;
use Vatly\Fluent\BillableFactory;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Exceptions\IncompleteWiring;
use Vatly\Fluent\OrderHandle;
use Vatly\Fluent\SubscriptionHandle;
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

    public function test_billable_factory_is_cached(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertSame($vatly->billableFactory(), $vatly->billableFactory());
    }

    // --- IncompleteWiring on missing dependencies ---

    public function test_billable_factory_throws_when_subscriptions_missing(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'BillableFactory'.*'subscriptions'/");

        $vatly->billableFactory();
    }

    public function test_billable_factory_throws_when_customers_missing(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
        ));

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'customers'/");

        $vatly->billableFactory();
    }

    public function test_billable_factory_throws_when_orders_missing(): void
    {
        $vatly = new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            customers: Mockery::mock(CustomerRepositoryInterface::class),
        ));

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'orders'/");

        $vatly->billableFactory();
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

    // --- Happy-path resolution ---

    public function test_billable_returns_orchestrator_for_owner(): void
    {
        $vatly = $this->fullyWiredVatly();

        $owner = Mockery::mock(BillableInterface::class);

        $billable = $vatly->billable($owner);

        $this->assertInstanceOf(Billable::class, $billable);
        $this->assertSame($owner, $billable->owner());
    }

    public function test_billable_factory_is_built_from_wiring(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertInstanceOf(BillableFactory::class, $vatly->billableFactory());
    }

    public function test_webhook_processor_is_built_from_wiring(): void
    {
        $vatly = $this->fullyWiredVatly();

        $this->assertInstanceOf(WebhookProcessor::class, $vatly->webhookProcessor());
    }

    public function test_subscription_handle_wraps_the_given_subscription(): void
    {
        $vatly = $this->fullyWiredVatly();
        $subscription = Mockery::mock(SubscriptionInterface::class);

        $handle = $vatly->subscriptionHandle($subscription);

        $this->assertInstanceOf(SubscriptionHandle::class, $handle);
        $this->assertSame($subscription, $handle->model());
    }

    public function test_subscription_handle_throws_when_subscriptions_missing(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');

        $this->expectException(IncompleteWiring::class);
        $this->expectExceptionMessageMatches("/'SubscriptionHandle'.*'subscriptions'/");

        $vatly->subscriptionHandle(Mockery::mock(SubscriptionInterface::class));
    }

    public function test_order_handle_wraps_the_given_order(): void
    {
        $vatly = Vatly::apiOnly('test_abcdefghijklmnopqrstuvwxyz');
        $order = Mockery::mock(OrderInterface::class);

        $handle = $vatly->orderHandle($order);

        $this->assertInstanceOf(OrderHandle::class, $handle);
        $this->assertSame($order, $handle->model());
    }

    private function fullyWiredVatly(): Vatly
    {
        return new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            customers: Mockery::mock(CustomerRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            events: Mockery::mock(EventDispatcherInterface::class),
        ));
    }
}
