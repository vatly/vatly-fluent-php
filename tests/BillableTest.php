<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use ReflectionClass;
use Vatly\API\Resources\Customer;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\CreateSubscriptionBillingUpdateLink;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Billable;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Exceptions\CustomerAlreadyCreatedException;
use Vatly\Fluent\Exceptions\InvalidCustomerException;
use Vatly\Fluent\SubscriptionHandle;

class BillableTest extends TestCase
{
    public function test_checkout_returns_a_builder_bound_to_the_owner(): void
    {
        $billable = $this->buildBillable();

        $this->assertInstanceOf(CheckoutBuilder::class, $billable->checkout());
    }

    public function test_subscribe_returns_a_subscription_builder(): void
    {
        $config = Mockery::mock(ConfigurationInterface::class);
        $config->shouldReceive('getDefaultRedirectUrlSuccess')->andReturn('https://app/done');
        $config->shouldReceive('getDefaultRedirectUrlCanceled')->andReturn('https://app/back');

        $billable = $this->buildBillable(config: $config);

        $this->assertInstanceOf(SubscriptionBuilder::class, $billable->subscribe());
    }

    public function test_subscribed_delegates_to_the_repository(): void
    {
        $owner = $this->stubOwner();

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('ownerHasActiveSubscription')
            ->with($owner, 'default')
            ->andReturn(true);

        $billable = $this->buildBillable(owner: $owner, subscriptions: $subscriptions);

        $this->assertTrue($billable->subscribed('default'));
    }

    public function test_subscription_returns_null_when_none_exists(): void
    {
        $owner = $this->stubOwner();

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('findByOwnerAndType')
            ->with($owner, 'default')
            ->andReturn(null);

        $billable = $this->buildBillable(owner: $owner, subscriptions: $subscriptions);

        $this->assertNull($billable->subscription('default'));
    }

    public function test_subscription_returns_a_handle_when_present(): void
    {
        $owner = $this->stubOwner();
        $found = Mockery::mock(SubscriptionInterface::class);

        $subscriptions = Mockery::mock(SubscriptionRepositoryInterface::class);
        $subscriptions->shouldReceive('findByOwnerAndType')
            ->with($owner, 'default')
            ->andReturn($found);

        $billable = $this->buildBillable(owner: $owner, subscriptions: $subscriptions);

        $handle = $billable->subscription('default');

        $this->assertInstanceOf(SubscriptionHandle::class, $handle);
        $this->assertSame($found, $handle->model());
    }

    public function test_orders_delegates_to_the_repository(): void
    {
        $owner = $this->stubOwner();
        $orderA = Mockery::mock(OrderInterface::class);
        $orderB = Mockery::mock(OrderInterface::class);

        $orders = Mockery::mock(OrderRepositoryInterface::class);
        $orders->shouldReceive('findAllByOwner')->with($owner)->andReturn([$orderA, $orderB]);

        $billable = $this->buildBillable(owner: $owner, orders: $orders);

        $this->assertSame([$orderA, $orderB], $billable->orders());
    }

    public function test_create_as_vatly_customer_creates_persists_and_returns_the_customer(): void
    {
        $owner = Mockery::mock(BillableInterface::class);
        $owner->shouldReceive('hasVatlyId')->andReturn(false);
        $owner->shouldReceive('getVatlyEmail')->andReturn('sander@example.test');
        $owner->shouldReceive('getVatlyName')->andReturn('Sander');
        $owner->shouldReceive('setVatlyId')->once()->with('customer_xyz');

        $customer = $this->makeCustomer(['id' => 'customer_xyz']);

        $action = Mockery::mock(CreateCustomer::class);
        $action->shouldReceive('execute')
            ->once()
            ->with(['email' => 'sander@example.test', 'name' => 'Sander'])
            ->andReturn($customer);

        $customers = Mockery::mock(CustomerRepositoryInterface::class);
        $customers->shouldReceive('save')->once()->with($owner);

        $billable = $this->buildBillable(
            owner: $owner,
            customers: $customers,
            createCustomerAction: $action,
        );

        $result = $billable->createAsVatlyCustomer();

        $this->assertSame('customer_xyz', $result->id);
    }

    public function test_create_as_vatly_customer_throws_when_already_linked(): void
    {
        $owner = $this->stubOwner(hasVatlyId: true);
        $owner->shouldReceive('getVatlyId')->andReturn('customer_xyz');

        $billable = $this->buildBillable(owner: $owner);

        $this->expectException(CustomerAlreadyCreatedException::class);

        $billable->createAsVatlyCustomer();
    }

    public function test_as_vatly_customer_fetches_via_get_customer_action(): void
    {
        $owner = Mockery::mock(BillableInterface::class);
        $owner->shouldReceive('hasVatlyId')->andReturn(true);
        $owner->shouldReceive('getVatlyId')->andReturn('customer_xyz');

        $customer = $this->makeCustomer(['id' => 'customer_xyz']);

        $action = Mockery::mock(GetCustomer::class);
        $action->shouldReceive('execute')->with('customer_xyz')->andReturn($customer);

        $billable = $this->buildBillable(owner: $owner, getCustomerAction: $action);

        $this->assertSame($customer, $billable->asVatlyCustomer());
    }

    public function test_as_vatly_customer_throws_when_no_vatly_id(): void
    {
        $owner = $this->stubOwner(hasVatlyId: false);

        $billable = $this->buildBillable(owner: $owner);

        $this->expectException(InvalidCustomerException::class);

        $billable->asVatlyCustomer();
    }

    public function test_ensure_has_vatly_customer_is_a_noop_when_already_linked(): void
    {
        $owner = $this->stubOwner(hasVatlyId: true);

        $customers = Mockery::mock(CustomerRepositoryInterface::class);
        $customers->shouldNotReceive('save');

        $action = Mockery::mock(CreateCustomer::class);
        $action->shouldNotReceive('execute');

        $billable = $this->buildBillable(
            owner: $owner,
            customers: $customers,
            createCustomerAction: $action,
        );

        $billable->ensureHasVatlyCustomer();
    }

    private function stubOwner(bool $hasVatlyId = false): BillableInterface
    {
        $owner = Mockery::mock(BillableInterface::class);
        $owner->shouldReceive('hasVatlyId')->andReturn($hasVatlyId);

        return $owner;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeCustomer(array $fields): Customer
    {
        /** @var Customer $resource */
        $resource = (new ReflectionClass(Customer::class))->newInstanceWithoutConstructor();

        foreach ($fields as $key => $value) {
            $resource->{$key} = $value;
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function buildBillable(
        ?BillableInterface $owner = null,
        ?SubscriptionRepositoryInterface $subscriptions = null,
        ?CustomerRepositoryInterface $customers = null,
        ?OrderRepositoryInterface $orders = null,
        ?ConfigurationInterface $config = null,
        ?CreateCheckout $createCheckoutAction = null,
        ?CreateCustomer $createCustomerAction = null,
        ?GetCustomer $getCustomerAction = null,
        ?GetSubscription $getSubscriptionAction = null,
        ?SwapSubscriptionPlan $swapSubscriptionPlanAction = null,
        ?CancelSubscription $cancelSubscriptionAction = null,
        ?CreateSubscriptionBillingUpdateLink $createBillingUpdateLinkAction = null,
    ): Billable {
        return new Billable(
            owner: $owner ?? $this->stubOwner(),
            subscriptions: $subscriptions ?? Mockery::mock(SubscriptionRepositoryInterface::class),
            customers: $customers ?? Mockery::mock(CustomerRepositoryInterface::class),
            orders: $orders ?? Mockery::mock(OrderRepositoryInterface::class),
            config: $config ?? Mockery::mock(ConfigurationInterface::class),
            createCheckoutAction: $createCheckoutAction ?? Mockery::mock(CreateCheckout::class),
            createCustomerAction: $createCustomerAction ?? Mockery::mock(CreateCustomer::class),
            getCustomerAction: $getCustomerAction ?? Mockery::mock(GetCustomer::class),
            getSubscriptionAction: $getSubscriptionAction ?? Mockery::mock(GetSubscription::class),
            swapSubscriptionPlanAction: $swapSubscriptionPlanAction ?? Mockery::mock(SwapSubscriptionPlan::class),
            cancelSubscriptionAction: $cancelSubscriptionAction ?? Mockery::mock(CancelSubscription::class),
            createBillingUpdateLinkAction: $createBillingUpdateLinkAction
                ?? Mockery::mock(CreateSubscriptionBillingUpdateLink::class),
        );
    }
}
