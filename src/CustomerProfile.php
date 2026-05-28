<?php

declare(strict_types=1);

namespace Vatly\Fluent;

/**
 * The minimum profile information fluent needs to create a Vatly customer
 * or address a known one. Decoupled from any host-side "user" shape.
 *
 * `vatlyId` is populated once the host knows the customer exists at Vatly;
 * before that, the profile carries only the email/name that will be sent
 * to the API.
 */
final class CustomerProfile
{
    public function __construct(
        public readonly ?string $vatlyId = null,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
    ) {
    }

    /**
     * Strips nulls so the API payload is minimal.
     *
     * @return array<string, string>
     */
    public function toPayload(): array
    {
        return array_filter([
            'email' => $this->email,
            'name'  => $this->name,
        ], fn ($v) => $v !== null);
    }
}
