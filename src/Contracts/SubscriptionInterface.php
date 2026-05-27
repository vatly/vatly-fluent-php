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
     * Get the owner of this subscription.
     */
    public function getOwner(): BillableInterface;
}
