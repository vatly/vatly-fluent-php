<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\Resources\Order as ApiOrder;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\ChargebackInterface;
use Vatly\Fluent\Contracts\ChargebackReader;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderLineInterface;
use Vatly\Fluent\Contracts\OrderLineReader;
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
        /**
         * Optional: supplied by {@see Vatly::order()} when a chargeback
         * repository is wired. Without it, {@see self::chargebacks()} returns an
         * empty array.
         */
        private readonly ?ChargebackReader $chargebacks = null,
        /**
         * Optional: supplied by {@see Vatly::order()} when an order-line
         * repository is wired. Without it, {@see self::lines()} returns an empty
         * array rather than reaching into driver internals.
         */
        private readonly ?OrderLineReader $orderLines = null,
    ) {
        //
    }

    private ?ApiOrder $apiOrderCache = null;

    /**
     * Fetch the live API {@see ApiOrder}, memoized per handle instance.
     *
     * The first call hits the Vatly API; subsequent calls (across
     * `invoiceUrl()` and the reversal helpers) reuse the same fetch.
     */
    private function apiOrder(): ApiOrder
    {
        return $this->apiOrderCache ??= $this->getOrderAction->execute($this->order->getVatlyId());
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

    public function isPaid(): bool
    {
        return $this->order->isPaid();
    }

    // --- Operations ---

    /**
     * Fetch the hosted invoice URL from the Vatly API.
     *
     * Reads the live API order, which is fetched once and memoized per handle
     * instance (shared with the reversal helpers below). Returns null when the
     * upstream order doesn't (yet) have an invoice attached.
     */
    public function invoiceUrl(): ?string
    {
        return $this->apiOrder()->links->customerInvoice?->href;
    }

    // --- API-sourced reversal helpers ---
    //
    // These read the live API Order rather than synthesizing a local status:
    // the order's own `status` stays terminal `paid` even after a reversal.
    // The API's `reversedSubtotal` combines refunds and chargebacks, so these
    // surface "did money come back" regardless of how it came back. The
    // underlying API order is fetched once and memoized per handle instance.

    /**
     * Subtotal (net of tax, in integer cents) that has been reversed —
     * refunded and/or charged back — per the live API order.
     */
    public function reversedSubtotal(): int
    {
        return $this->apiOrder()->reversedSubtotal->toCents();
    }

    /**
     * Subtotal (net of tax, in integer cents) still available to reverse per
     * the live API order.
     */
    public function refundableSubtotal(): int
    {
        return $this->apiOrder()->refundableSubtotal->toCents();
    }

    /**
     * Whether any of the order's subtotal has been reversed.
     */
    public function isReversed(): bool
    {
        return $this->apiOrder()->isReversed();
    }

    /**
     * Whether the order is reversed but not in full.
     */
    public function isPartiallyReversed(): bool
    {
        return $this->apiOrder()->isPartiallyReversed();
    }

    /**
     * Whether the order's full subtotal has been reversed.
     */
    public function isFullyReversed(): bool
    {
        return $this->apiOrder()->isFullyReversed();
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

    /**
     * The chargebacks recorded locally against this order, per the driver's
     * {@see ChargebackReader} implementation.
     *
     * Reads only local state (no API call). Returns an empty array when no
     * chargeback repository is wired.
     *
     * @return ChargebackInterface[]
     */
    public function chargebacks(): array
    {
        return $this->chargebacks?->listForOrder($this->order->getVatlyId()) ?? [];
    }

    /**
     * The lines recorded locally for this order, per the driver's
     * {@see OrderLineReader} implementation.
     *
     * Reads only local state (no API call). Returns an empty array when no
     * order-line repository is wired, so callers can render line-item detail
     * unconditionally without feature-detecting the wiring.
     *
     * @return OrderLineInterface[]
     */
    public function lines(): array
    {
        return $this->orderLines?->listForOrder($this->order->getVatlyId()) ?? [];
    }
}
