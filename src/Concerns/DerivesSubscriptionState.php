<?php

declare(strict_types=1);

namespace Vatly\Fluent\Concerns;

use DateTimeImmutable;

/**
 * Default implementations of the derived state predicates declared on
 * {@see \Vatly\Fluent\Contracts\SubscriptionInterface}.
 *
 * Drivers MAY use this trait to satisfy the predicate methods. A driver
 * that needs different semantics — e.g. a SQL-side `WHERE ends_at IS NULL
 * OR ends_at > NOW()` for cheap active-checks at the query level — can
 * simply implement any subset of the predicates itself; PHP trait conflict
 * resolution favors the class's own definition.
 *
 * Requires the using class to implement `getEndsAt(): ?DateTimeInterface`
 * from `SubscriptionInterface`. All other predicates compose from there.
 */
trait DerivesSubscriptionState
{
    public function isCancelled(): bool
    {
        return $this->getEndsAt() !== null;
    }

    public function isOnGracePeriod(): bool
    {
        $endsAt = $this->getEndsAt();

        return $endsAt !== null && $endsAt > new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return ! $this->isCancelled() || $this->isOnGracePeriod();
    }

    public function isValid(): bool
    {
        // Equivalent to isActive() today. When trials land, this becomes
        // isActive() || isOnTrial().
        return $this->isActive();
    }

    public function isRecurring(): bool
    {
        return ! $this->isCancelled();
    }

    public function isEnded(): bool
    {
        return $this->isCancelled() && ! $this->isOnGracePeriod();
    }
}
