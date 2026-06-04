<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

use DateTimeInterface;

/**
 * Interface for subscription entities.
 */
interface SubscriptionInterface
{
    /**
     * Get the Vatly subscription ID.
     */
    public function getVatlyId(): string;

    /**
     * Get the subscription type/name.
     */
    public function getType(): string;

    /**
     * Get the plan ID.
     */
    public function getPlanId(): string;

    /**
     * Get the subscription name.
     */
    public function getName(): string;

    /**
     * Get the quantity.
     */
    public function getQuantity(): int;

    /**
     * Get the date when the subscription ends (if cancelled).
     */
    public function getEndsAt(): ?DateTimeInterface;

    /**
     * Check if the subscription is cancelled.
     */
    public function isCancelled(): bool;

    /**
     * Check if the subscription is on a grace period.
     */
    public function isOnGracePeriod(): bool;

    /**
     * Check if the subscription is active (not cancelled, or on grace period).
     */
    public function isActive(): bool;

    /**
     * Check if the subscription is currently usable.
     *
     * Today equivalent to {@see self::isActive()}. Reserved as a broader
     * umbrella that will also account for trials once those land.
     */
    public function isValid(): bool;

    /**
     * Check if the subscription is recurring — i.e. not (yet) cancelled and
     * will renew at the next billing cycle.
     */
    public function isRecurring(): bool;

    /**
     * Check if the subscription has fully ended — cancelled and past the
     * grace period.
     */
    public function isEnded(): bool;

    /**
     * Get the payment method category on file for this subscription.
     *
     * Normalized per {@see \Vatly\API\Types\Mandate::$method}:
     * `card`, `sepa_debit`, `paypal`, `bacs_debit`, etc.
     *
     * Returns `null` when the subscription has no mandate yet (e.g.
     * ended-before-payment) or when the driver hasn't synced from Vatly.
     */
    public function getMandateMethod(): ?string;

    /**
     * Get the customer-facing masked identifier for the payment method on
     * file — e.g. `4242` (card last 4) or `NL91****4300` (masked IBAN).
     *
     * Returns `null` when no mandate exists, when the mandate type has no
     * identifier (e.g. PayPal), or when the driver hasn't synced.
     */
    public function getMandateMaskedIdentifier(): ?string;

    /**
     * Whether this subscription was created in test mode (vs live).
     */
    public function isTestmode(): bool;
}
