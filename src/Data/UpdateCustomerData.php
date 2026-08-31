<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

/**
 * Identity fields for updating a Vatly customer: `name` and `email` only.
 *
 * These are the 1:1 customer fields that carry no tax / compliance consequence,
 * so they can be changed directly (unlike billing-address details, which stay on
 * the hosted billing-update flow — see
 * {@see \Vatly\Fluent\SubscriptionHandle::updateBilling()}).
 *
 * Following the repo's DTO convention (cf. {@see \Vatly\Fluent\CustomerProfile}),
 * a `null` field means "leave unchanged": {@see self::toPayload()} strips nulls,
 * so only the fields you set are sent. Pass a plain array to
 * {@see \Vatly\Fluent\CustomerService::update()} instead if you need to send an
 * explicit `null` (e.g. to clear the name).
 *
 * @immutable
 */
final class UpdateCustomerData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {
    }

    /**
     * The API payload, with unset (null) fields stripped.
     *
     * @return array<string, string>
     */
    public function toPayload(): array
    {
        return array_filter([
            'name'  => $this->name,
            'email' => $this->email,
        ], fn ($v) => $v !== null);
    }
}
