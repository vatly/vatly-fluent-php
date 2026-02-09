<?php

declare(strict_types=1);

namespace Vatly\Contracts;

/**
 * Interface for entities that can be billed (customers).
 *
 * Implement this interface on your User model or any entity
 * that should be able to subscribe and make payments.
 */
interface BillableInterface
{
    /**
     * Get the Vatly customer ID.
     */
    public function getVatlyId(): ?string;

    /**
     * Set the Vatly customer ID.
     */
    public function setVatlyId(string $id): void;

    /**
     * Check if this billable has a Vatly customer ID.
     */
    public function hasVatlyId(): bool;

    /**
     * Get the email address for Vatly.
     */
    public function getVatlyEmail(): ?string;

    /**
     * Get the name for Vatly.
     */
    public function getVatlyName(): ?string;

    /**
     * Get the primary key of this billable entity.
     */
    public function getKey(): string|int;

    /**
     * Get the morph class name for polymorphic relationships.
     */
    public function getMorphClass(): string;

    /**
     * Persist the billable entity.
     */
    public function save(): void;
}
