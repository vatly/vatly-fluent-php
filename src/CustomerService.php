<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\Resources\Customer as ApiCustomer;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;

/**
 * Customer-related operations: create + bind, fetch, attribute.
 *
 * Reached via {@see Vatly::customers()}. Composes a {@see CreateCustomer}
 * and {@see GetCustomer} action with a driver-supplied
 * {@see CustomerBindingRepository} so callers don't have to remember to
 * record the host ↔ Vatly link after creating a customer.
 */
class CustomerService
{
    public function __construct(
        private CreateCustomer $createCustomer,
        private GetCustomer $getCustomer,
        private CustomerBindingRepository $bindings,
    ) {
    }

    /**
     * Host-first: create a Vatly customer for a known host entity and bind.
     *
     * Always creates a new Vatly customer; any `vatlyId` on the supplied
     * `CustomerProfile` is ignored. Use {@see self::attribute()} to link
     * an existing Vatly customer to a host instead.
     *
     * @throws CustomerAlreadyBoundException When the host customer id is already bound.
     */
    public function createFor(string $hostCustomerId, CustomerProfile $profile): ApiCustomer
    {
        if (($existing = $this->bindings->vatlyCustomerIdFor($hostCustomerId)) !== null) {
            throw CustomerAlreadyBoundException::onCreate($hostCustomerId, $existing);
        }

        $customer = $this->createCustomer->execute($profile->toPayload());
        $this->bindings->bind($customer->id, $hostCustomerId);

        return $customer;
    }

    /** Anonymous: create a Vatly customer with no host attribution. */
    public function createUnattributed(CustomerProfile $profile): ApiCustomer
    {
        $customer = $this->createCustomer->execute($profile->toPayload());
        $this->bindings->record($customer->id);

        return $customer;
    }

    /**
     * Link an already-known Vatly customer to a host customer id.
     *
     * Idempotent for the exact pair (binding the same vatly id to the same
     * host id twice is a no-op). Throws when the host is already bound to
     * a different Vatly customer — silent overwrites have to go through
     * the binding repo directly.
     *
     * @throws CustomerAlreadyBoundException When the host is already bound to a different Vatly customer.
     */
    public function attribute(string $vatlyCustomerId, string $hostCustomerId): void
    {
        $existing = $this->bindings->vatlyCustomerIdFor($hostCustomerId);

        if ($existing !== null && $existing !== $vatlyCustomerId) {
            throw CustomerAlreadyBoundException::onAttribute($hostCustomerId, $vatlyCustomerId, $existing);
        }

        $this->bindings->bind($vatlyCustomerId, $hostCustomerId);
    }

    /** Fetch the Vatly customer linked to this host customer id, or null. */
    public function findByHostCustomerId(string $hostCustomerId): ?ApiCustomer
    {
        $vatlyCustomerId = $this->bindings->vatlyCustomerIdFor($hostCustomerId);

        return $vatlyCustomerId !== null ? $this->getCustomer->execute($vatlyCustomerId) : null;
    }

    public function findByVatlyCustomerId(string $vatlyCustomerId): ApiCustomer
    {
        return $this->getCustomer->execute($vatlyCustomerId);
    }

    public function hostCustomerIdFor(string $vatlyCustomerId): ?string
    {
        return $this->bindings->hostCustomerIdFor($vatlyCustomerId);
    }
}
