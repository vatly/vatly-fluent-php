<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\ResumeSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Billable;
use Vatly\Fluent\BillableFactory;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;

class BillableFactoryTest extends TestCase
{
    public function test_for_owner_returns_a_billable_bound_to_the_given_owner(): void
    {
        $owner = Mockery::mock(BillableInterface::class);

        $factory = new BillableFactory(
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            customers: Mockery::mock(CustomerRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            config: Mockery::mock(ConfigurationInterface::class),
            createCheckoutAction: Mockery::mock(CreateCheckout::class),
            createCustomerAction: Mockery::mock(CreateCustomer::class),
            getCustomerAction: Mockery::mock(GetCustomer::class),
            getOrderAction: Mockery::mock(GetOrder::class),
            getSubscriptionAction: Mockery::mock(GetSubscription::class),
            swapSubscriptionPlanAction: Mockery::mock(SwapSubscriptionPlan::class),
            cancelSubscriptionAction: Mockery::mock(CancelSubscription::class),
            resumeSubscriptionAction: Mockery::mock(ResumeSubscription::class),
            updateBillingAction: Mockery::mock(UpdateSubscriptionBilling::class),
        );

        $billable = $factory->forOwner($owner);

        $this->assertInstanceOf(Billable::class, $billable);
        $this->assertSame($owner, $billable->owner());
    }

    public function test_for_owner_returns_a_fresh_billable_each_call(): void
    {
        $factory = new BillableFactory(
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            customers: Mockery::mock(CustomerRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            config: Mockery::mock(ConfigurationInterface::class),
            createCheckoutAction: Mockery::mock(CreateCheckout::class),
            createCustomerAction: Mockery::mock(CreateCustomer::class),
            getCustomerAction: Mockery::mock(GetCustomer::class),
            getOrderAction: Mockery::mock(GetOrder::class),
            getSubscriptionAction: Mockery::mock(GetSubscription::class),
            swapSubscriptionPlanAction: Mockery::mock(SwapSubscriptionPlan::class),
            cancelSubscriptionAction: Mockery::mock(CancelSubscription::class),
            resumeSubscriptionAction: Mockery::mock(ResumeSubscription::class),
            updateBillingAction: Mockery::mock(UpdateSubscriptionBilling::class),
        );

        $ownerA = Mockery::mock(BillableInterface::class);
        $ownerB = Mockery::mock(BillableInterface::class);

        $billableA = $factory->forOwner($ownerA);
        $billableB = $factory->forOwner($ownerB);

        $this->assertNotSame($billableA, $billableB);
        $this->assertSame($ownerA, $billableA->owner());
        $this->assertSame($ownerB, $billableB->owner());
    }
}
