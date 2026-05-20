<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\Resources\Customer;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\CreateSubscriptionBillingUpdateLink;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
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

/**
 * The per-owner Vatly orchestrator — the canonical API surface.
 *
 * Drivers expose this through a framework-idiomatic accessor on the host
 * entity (e.g. `$user->vatlyBillable()`) that returns one of these.
 */
class Billable
{
    public function __construct(
        private BillableInterface $owner,
        private SubscriptionRepositoryInterface $subscriptions,
        private CustomerRepositoryInterface $customers,
        private OrderRepositoryInterface $orders,
        private ConfigurationInterface $config,
        private CreateCheckout $createCheckoutAction,
        private CreateCustomer $createCustomerAction,
        private GetCustomer $getCustomerAction,
        private GetSubscription $getSubscriptionAction,
        private SwapSubscriptionPlan $swapSubscriptionPlanAction,
        private CancelSubscription $cancelSubscriptionAction,
        private CreateSubscriptionBillingUpdateLink $createBillingUpdateLinkAction,
    ) {
        //
    }

    public function owner(): BillableInterface
    {
        return $this->owner;
    }

    // --- Customer ---

    /**
     * Create the corresponding Vatly customer for this owner. Persists the
     * resulting customer ID on the owner via CustomerRepository::save().
     *
     * @param array<string, mixed> $options Additional Vatly customer payload (email/name overrides etc.).
     *
     * @throws CustomerAlreadyCreatedException
     */
    public function createAsVatlyCustomer(array $options = []): Customer
    {
        if ($this->owner->hasVatlyId()) {
            throw CustomerAlreadyCreatedException::exists($this->owner);
        }

        if (! array_key_exists('email', $options) && $email = $this->owner->getVatlyEmail()) {
            $options['email'] = $email;
        }

        if (! array_key_exists('name', $options) && $name = $this->owner->getVatlyName()) {
            $options['name'] = $name;
        }

        $customer = $this->createCustomerAction->execute($options);

        $this->owner->setVatlyId($customer->id);
        $this->customers->save($this->owner);

        return $customer;
    }

    /**
     * Fetch this owner's current Vatly customer.
     *
     * @throws InvalidCustomerException When no Vatly customer ID is set.
     */
    public function asVatlyCustomer(): Customer
    {
        $this->assertCustomerExists();

        return $this->getCustomerAction->execute((string) $this->owner->getVatlyId());
    }

    /**
     * Return the existing Vatly customer if one is linked, otherwise create one.
     *
     * @param array<string, mixed> $options
     */
    public function createOrGetVatlyCustomer(array $options = []): Customer
    {
        if ($this->owner->hasVatlyId()) {
            return $this->asVatlyCustomer();
        }

        return $this->createAsVatlyCustomer($options);
    }

    private function assertCustomerExists(): void
    {
        if (! $this->owner->hasVatlyId()) {
            throw InvalidCustomerException::notYetCreated($this->owner);
        }
    }

    // --- Checkouts ---

    public function checkout(): CheckoutBuilder
    {
        return new CheckoutBuilder(
            owner: $this->owner,
            createCheckout: $this->createCheckoutAction,
        );
    }

    // --- Subscriptions ---

    public function subscribe(): SubscriptionBuilder
    {
        return new SubscriptionBuilder(
            config: $this->config,
            owner: $this->owner,
            checkoutBuilder: $this->checkout(),
        );
    }

    public function subscribed(string $type = 'default'): bool
    {
        return $this->subscriptions->ownerHasActiveSubscription($this->owner, $type);
    }

    public function subscription(string $type = 'default'): ?SubscriptionHandle
    {
        $subscription = $this->subscriptions->findByOwnerAndType($this->owner, $type);

        if ($subscription === null) {
            return null;
        }

        return $this->handleFor($subscription);
    }

    private function handleFor(SubscriptionInterface $subscription): SubscriptionHandle
    {
        return new SubscriptionHandle(
            subscription: $subscription,
            subscriptions: $this->subscriptions,
            swapAction: $this->swapSubscriptionPlanAction,
            cancelAction: $this->cancelSubscriptionAction,
            getSubscriptionAction: $this->getSubscriptionAction,
            createBillingUpdateLinkAction: $this->createBillingUpdateLinkAction,
        );
    }

    // --- Orders ---

    /**
     * @return OrderInterface[]
     */
    public function orders(): array
    {
        return $this->orders->findAllByOwner($this->owner);
    }
}
