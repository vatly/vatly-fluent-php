<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Persists the link between a Vatly customer id and a host-side customer id.
 *
 * Bidirectional but bidirectionally-optional. A row may be recorded with
 * only a Vatly id (anonymous-checkout flow); the host customer id can be
 * attributed later. Driver implementations decide where the link is
 * stored — a column on the host's user table, a dedicated join table,
 * user meta, etc.
 */
interface CustomerBindingRepository
{
    /** Bind a Vatly customer to a host entity. Idempotent. */
    public function bind(string $vatlyCustomerId, string $hostCustomerId): void;

    /** Record a Vatly customer with no host attribution yet. Idempotent. */
    public function record(string $vatlyCustomerId): void;

    /** Host customer id bound to this Vatly customer id, or null. */
    public function hostCustomerIdFor(string $vatlyCustomerId): ?string;

    /** Vatly customer id bound to this host customer id, or null. */
    public function vatlyCustomerIdFor(string $hostCustomerId): ?string;
}
