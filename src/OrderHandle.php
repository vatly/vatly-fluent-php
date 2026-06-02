<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\RefundInterface;
use Vatly\Fluent\Contracts\RefundReader;

/**
 * Framework-agnostic operations on an order.
 *
 * Wraps an {@see OrderInterface} (the persistent state, owned by the driver)
 * with the actions that operate on it. Drivers expose this via
 * {@see Vatly::order()}.
 *
 * Mirrors {@see SubscriptionHandle} for orders. State accessors delegate
 * to the underlying {@see OrderInterface}; operations (e.g. `invoiceUrl()`)
 * reach the Vatly API through injected actions.
 */
class OrderHandle
{
    public function __construct(
        private readonly OrderInterface $order,
        private readonly GetOrder $getOrderAction,
        /**
         * Optional: supplied by {@see Vatly::order()} when a refund repository
         * is wired. Without it, {@see self::refunds()} returns an empty array
         * rather than reaching into driver internals.
         */
        private readonly ?RefundReader $refunds = null,
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

    public function getSubtotal(): ?int
    {
        return $this->order->getSubtotal();
    }

    public function getCurrency(): string
    {
        return $this->order->getCurrency();
    }

    public function getPaymentMethod(): ?string
    {
        return $this->order->getPaymentMethod();
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

    /**
     * The refunds recorded locally against this order, newest-first per the
     * driver's {@see RefundReader} implementation.
     *
     * Reads only local state (no API call). Returns an empty array when no
     * refund repository is wired, so callers can render a refunds section
     * unconditionally without feature-detecting the wiring.
     *
     * @return RefundInterface[]
     */
    public function refunds(): array
    {
        return $this->refunds?->listForOrder($this->order->getVatlyId()) ?? [];
    }
}
