<?php

declare(strict_types=1);

namespace Vatly\Contracts;

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
     * Get the owner of this subscription.
     */
    public function getOwner(): BillableInterface;
}
