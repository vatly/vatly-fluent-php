<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

/**
 * Redacts the personal data in a webhook's `object` before it is recorded in
 * the webhook-call log: the customer's name, email, address, tax ID and
 * payment method details. Keys and nesting stay as they are; only string
 * values inside the personal fields become {@see self::REDACTED}. The event
 * passed to reactions and listeners is built from the unredacted object.
 */
final class WebhookCallRedactor
{
    public const REDACTED = '[redacted]';

    /** Top-level fields of Vatly's event objects that hold personal data. */
    public const PERSONAL_FIELDS = ['customerDetails', 'billingAddress', 'shippingAddress', 'mandate'];

    /**
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    public static function redact(array $object): array
    {
        foreach (self::PERSONAL_FIELDS as $field) {
            if (array_key_exists($field, $object)) {
                $object[$field] = self::redactValue($object[$field]);
            }
        }

        return $object;
    }

    private static function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::redactValue(...), $value);
        }

        return is_string($value) ? self::REDACTED : $value;
    }
}
