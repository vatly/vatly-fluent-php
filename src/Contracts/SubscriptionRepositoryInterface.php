<?php

declare(strict_types=1);

namespace Vatly\Contracts;

/**
 * Interface for subscription persistence.
 */
interface SubscriptionRepositoryInterface
{
    /**
     * Find a subscription by its Vatly ID.
     */
    public function findByVatlyId(string $vatlyId): ?SubscriptionInterface;

    /**
     * Find a subscription by owner and type.
     */
    public function findByOwnerAndType(BillableInterface $owner, string $type): ?SubscriptionInterface;

    /**
     * Find all subscriptions for an owner.
     *
     * @return SubscriptionInterface[]
     */
    public function findAllByOwner(BillableInterface $owner): array;

    /**
     * Check if owner has an active subscription of a given type.
     */
    public function ownerHasActiveSubscription(BillableInterface $owner, string $type): bool;

    /**
     * Create a new subscription.
     */
    public function create(array $attributes): SubscriptionInterface;

    /**
     * Update a subscription.
     */
    public function update(SubscriptionInterface $subscription, array $attributes): SubscriptionInterface;
}
