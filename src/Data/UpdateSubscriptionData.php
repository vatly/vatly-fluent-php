<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

use DateTimeInterface;
use Vatly\API\Types\Mandate;

/**
 * Data for updating an existing subscription from Vatly.
 *
 * @immutable
 */
class UpdateSubscriptionData
{
    public function __construct(
        public ?string $planId = null,
        public ?string $name = null,
        public ?int $quantity = null,
        public ?DateTimeInterface $endsAt = null,
        public bool $clearEndsAt = false,
        /**
         * Raw Vatly status (e.g. "trialing", "active", "past_due"). Passed
         * through verbatim — drivers are responsible for mapping to their
         * host's status vocabulary. Null means "no status change".
         */
        public ?string $status = null,
        /**
         * Atomic mandate replacement. Non-null = the driver writes the whole
         * Mandate object (both method and maskedIdentifier, the latter may
         * be null for mandate types without an identifier like PayPal).
         * Null = "no mandate change" — pair with `clearMandate: true` below
         * to explicitly remove a stored mandate.
         *
         * The two parts (method, maskedIdentifier) are atomically bound:
         * passing a fresh Mandate replaces both, preventing mixed local
         * state like "paypal / 4242" (old card last4 lingering after a
         * card→paypal switch).
         */
        public ?Mandate $mandate = null,
        /**
         * When true, clears the mandate fields in the driver's storage.
         * Mirrors the `clearEndsAt` convention: a non-null `mandate` wins
         * over this flag, so passing a fresh Mandate is always a
         * replacement.
         *
         * Set by {@see \Vatly\Fluent\SubscriptionHandle::sync()} when the
         * live API returns `mandate: null` *and* the local copy has a
         * stored mandate — i.e. an observable removal. When local mandate
         * is already null, sync() leaves the clear flag false so a
         * freshly-subscribed customer's transient API null doesn't get
         * mistaken for a removal (see
         * {@see \Vatly\API\Types\Mandate::$maskedIdentifier}'s docblock).
         */
        public bool $clearMandate = false,
    ) {}
}
