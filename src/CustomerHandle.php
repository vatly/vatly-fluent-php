<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\Resources\Customer as ApiCustomer;
use Vatly\API\Types\PortalSession;
use Vatly\Fluent\Actions\CreateCustomerPortalSession;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\UpdateCustomer;
use Vatly\Fluent\Data\UpdateCustomerData;

/**
 * Framework-agnostic per-customer operations.
 *
 * Wraps a Vatly customer id with the customer-level operations that live at
 * Vatly (identity read/update, hosted-portal session). Mirrors
 * {@see SubscriptionHandle} / {@see OrderHandle}; drivers expose it via
 * {@see Vatly::customer()}.
 *
 * Unlike those handles, a customer has no driver-owned local state contract
 * (bindings map the id↔id link; there is no `CustomerInterface`), so the handle
 * wraps just the id and lazily fetches the api-php {@see ApiCustomer} resource —
 * memoized per instance — when an accessor needs it. Operations that don't need
 * the resource ({@see self::portalSession()}) never trigger that fetch.
 */
class CustomerHandle
{
    private ?ApiCustomer $customer = null;

    public function __construct(
        private readonly string $customerId,
        private readonly GetCustomer $getAction,
        private readonly UpdateCustomer $updateAction,
        private readonly CreateCustomerPortalSession $portalSessionAction,
    ) {
        //
    }

    /**
     * The Vatly customer id (e.g. `customer_7kBmRtPvXw2NjLhYcZaE`).
     */
    public function id(): string
    {
        return $this->customerId;
    }

    /**
     * The live api-php {@see ApiCustomer} resource, fetched once and memoized
     * per handle instance. Call {@see self::sync()} to force a refetch.
     */
    public function model(): ApiCustomer
    {
        return $this->resource();
    }

    /**
     * The customer's name on file, or `null` until one is set.
     */
    public function name(): ?string
    {
        return $this->resource()->name;
    }

    /**
     * The customer's email on file.
     */
    public function email(): ?string
    {
        return $this->resource()->email;
    }

    /**
     * Read back the customer's identity fields for rendering an "account
     * details" view.
     *
     * @return array{name: ?string, email: ?string}
     */
    public function identity(): array
    {
        $customer = $this->resource();

        return ['name' => $customer->name, 'email' => $customer->email];
    }

    // --- Operations ---

    /**
     * Update the customer's identity fields (`name`, `email`).
     *
     * Prefer the typed {@see UpdateCustomerData} DTO; a plain array is also
     * accepted (and is the way to send an explicit `null`, e.g. to clear the
     * name). Billing-address details are not editable here — amend those through
     * the hosted flow instead. Refreshes this handle's cached resource from the
     * update response.
     *
     * @param UpdateCustomerData|array<string, mixed> $data  Any of `name`, `email`.
     */
    public function update(UpdateCustomerData|array $data): self
    {
        $payload = $data instanceof UpdateCustomerData ? $data->toPayload() : $data;

        $this->customer = $this->updateAction->execute($this->customerId, $payload);

        return $this;
    }

    /**
     * Open a short-lived, single-use hosted customer portal session and return
     * it. Redirect the customer's browser to {@see PortalSession::$url}; the link
     * expires after roughly 15 minutes and can be consumed once, so do not cache
     * or log it. Pass `returnUrl` (an absolute HTTPS URL) in `$options` to render
     * a return link in the portal. Does not fetch the customer resource.
     *
     * @param array<string, mixed> $options  Optional body (`returnUrl`).
     */
    public function portalSession(array $options = []): PortalSession
    {
        return $this->portalSessionAction->execute($this->customerId, $options);
    }

    /**
     * Alias for {@see self::portalSession()}.
     *
     * @param array<string, mixed> $options  Optional body (`returnUrl`).
     */
    public function createPortalSession(array $options = []): PortalSession
    {
        return $this->portalSession($options);
    }

    /**
     * Refresh this handle's cached resource from Vatly.
     */
    public function sync(): self
    {
        $this->customer = $this->getAction->execute($this->customerId);

        return $this;
    }

    private function resource(): ApiCustomer
    {
        return $this->customer ??= $this->getAction->execute($this->customerId);
    }
}
