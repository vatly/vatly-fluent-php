<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\OrderInterface;

/**
 * Framework-agnostic operations on an order.
 *
 * Wraps an {@see OrderInterface} (the persistent state, owned by the driver)
 * with the actions that operate on it. Drivers expose this via
 * {@see Billable::order()}.
 *
 * Mirrors {@see SubscriptionHandle} for orders. State accessors delegate to
 * the underlying {@see OrderInterface}; operations (e.g. `invoiceUrl()`)
 * reach the Vatly API through injected actions.
 */
class OrderHandle
{
    public function __construct(
        private readonly OrderInterface $order,
        private readonly GetOrder $getOrderAction,
    ) {
        //
    }

    /**
     * The underlying persistent order record.
     */
    public function model(): OrderInterface
    {
        return $this->order;
    }

    // --- State accessors (delegate to OrderInterface) ---

    public function getVatlyId(): string
    {
        return $this->order->getVatlyId();
    }

    public function getStatus(): string
    {
        return $this->order->getStatus();
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->order->getInvoiceNumber();
    }

    public function getTotal(): int
    {
        return $this->order->getTotal();
    }

    public function getCurrency(): string
    {
        return $this->order->getCurrency();
    }

    public function getPaymentMethod(): ?string
    {
        return $this->order->getPaymentMethod();
    }

    public function getOwner(): BillableInterface
    {
        return $this->order->getOwner();
    }

    public function isPaid(): bool
    {
        return $this->order->isPaid();
    }

    // --- Operations ---

    /**
     * Fetch the hosted invoice URL from the Vatly API.
     *
     * Each call hits the API; cache at the call site if you need to render
     * many orders. Returns null when the upstream order doesn't (yet) have
     * an invoice attached.
     */
    public function invoiceUrl(): ?string
    {
        $apiOrder = $this->getOrderAction->execute($this->order->getVatlyId());

        return $apiOrder->links->customerInvoice?->href;
    }
}
