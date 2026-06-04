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

    /**
     * Whether this order was created in test mode (vs live).
     */
    public function isTestmode(): bool;
}
