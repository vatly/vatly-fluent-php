<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for order entities.
 */
interface OrderInterface
{
    /**
     * Get the Vatly order ID.
     */
    public function getVatlyId(): string;

    /**
     * Get the order status.
     */
    public function getStatus(): string;

    /**
     * Get the invoice number.
     */
    public function getInvoiceNumber(): ?string;

    /**
     * Get the order total.
     */
    public function getTotal(): int;

    /**
     * Get the order subtotal (net of tax), in integer cents.
     *
     * Returns `null` when the driver did not persist a subtotal (e.g. rows
     * written before subtotal tracking landed). The refund-status reaction
     * ({@see \Vatly\Fluent\Webhooks\Reactions\SyncOrderOnRefundChange}) needs
     * this to compare cumulative refunded subtotal against the order's own
     * subtotal; when it is `null` the reaction degrades to a conservative
     * "partially refunded" rather than claiming a full refund it can't verify.
     */
    public function getSubtotal(): ?int;

    /**
     * Get the currency.
     */
    public function getCurrency(): string;

    /**
     * Get the payment method.
     */
    public function getPaymentMethod(): ?string;

    /**
     * Check if the order is paid.
     */
    public function isPaid(): bool;
}
