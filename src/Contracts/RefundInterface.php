<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for refund entities.
 *
 * Mirrors {@see OrderInterface}: a thin read surface over the driver's own
 * persisted refund row, populated from Vatly's `refund.*` webhooks.
 */
interface RefundInterface
{
    /**
     * Get the Vatly refund ID.
     */
    public function getVatlyId(): string;

    /**
     * Get the raw Vatly refund status (e.g. "refunded", "failed", "canceled").
     */
    public function getStatus(): string;

    /**
     * Get the refund total, in integer cents.
     */
    public function getTotal(): int;

    /**
     * Get the currency.
     */
    public function getCurrency(): string;

    /**
     * Get the Vatly ID of the order the refund was issued against.
     */
    public function getOriginalOrderId(): string;

    /**
     * Whether the refund has completed (funds returned to the customer).
     */
    public function isCompleted(): bool;

    /**
     * Whether this refund was created in test mode (vs live).
     */
    public function isTestmode(): bool;
}
