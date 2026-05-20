<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\CreateSubscriptionBillingUpdateLink;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;

/**
 * Builds a Vatly\Fluent\Billable orchestrator bound to a specific owner.
 *
 * Drivers register this as a singleton in their DI container with all the
 * shared dependencies (repos, actions, config) and call ->forOwner($entity)
 * inside the per-owner accessor trait/method.
 */
class BillableFactory
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly CustomerRepositoryInterface $customers,
        private readonly OrderRepositoryInterface $orders,
        private readonly ConfigurationInterface $config,
        private readonly CreateCheckout $createCheckoutAction,
        private readonly CreateCustomer $createCustomerAction,
        private readonly GetCustomer $getCustomerAction,
        private readonly GetSubscription $getSubscriptionAction,
        private readonly SwapSubscriptionPlan $swapSubscriptionPlanAction,
        private readonly CancelSubscription $cancelSubscriptionAction,
        private readonly CreateSubscriptionBillingUpdateLink $createBillingUpdateLinkAction,
    ) {
        //
    }

    public function forOwner(BillableInterface $owner): Billable
    {
        return new Billable(
            owner: $owner,
            subscriptions: $this->subscriptions,
            customers: $this->customers,
            orders: $this->orders,
            config: $this->config,
            createCheckoutAction: $this->createCheckoutAction,
            createCustomerAction: $this->createCustomerAction,
            getCustomerAction: $this->getCustomerAction,
            getSubscriptionAction: $this->getSubscriptionAction,
            swapSubscriptionPlanAction: $this->swapSubscriptionPlanAction,
            cancelSubscriptionAction: $this->cancelSubscriptionAction,
            createBillingUpdateLinkAction: $this->createBillingUpdateLinkAction,
        );
    }
}
