<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for chargeback entities.
 *
 * Mirrors {@see RefundInterface}: a thin read surface over the driver's own
 * persisted chargeback row, populated from Vatly's `order.chargeback_*`
 * webhooks. Where a refund is a merchant-initiated return, a chargeback is a
 * bank-initiated dispute — higher impact, so consumers usually want it queryable
 * for finance ops and reconciliation.
 */
interface ChargebackInterface
{
    /**
     * Get the Vatly chargeback ID.
     */
    public function getVatlyId(): string;

    /**
     * Get the raw Vatly chargeback status (e.g. "pending", "won", "lost").
     */
    public function getStatus(): string;

    /**
     * Get the chargeback total, in integer cents.
     */
    public function getTotal(): int;

    /**
     * Get the currency.
     */
    public function getCurrency(): string;

    /**
     * Get the Vatly ID of the original order the chargeback was raised against.
     */
    public function getOriginalOrderId(): string;

    /**
     * Get the chargeback reason, when Vatly provided one.
     */
    public function getReason(): ?string;

    /**
     * Whether the chargeback has been reversed (resolved in the merchant's
     * favour — funds returned to the merchant).
     */
    public function isReversed(): bool;

    /**
     * Whether this chargeback was created in test mode (vs live).
     */
    public function isTestmode(): bool;
}
