<?php

declare(strict_types=1);

namespace Vatly\Fluent\Types;

use Vatly\API\Types\Money as ApiMoney;

/**
 * Internal helper for converting api-php's decimal-string Money into the
 * cents-as-int convention used everywhere else in fluent-php.
 */
final class Money
{
    /**
     * Convert a decimal-string value ("17.35") to integer cents (1735).
     * Avoids float-precision pitfalls of `(int) ((float) $v * 100)`.
     */
    public static function decimalToCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        if (str_contains($value, '.')) {
            [$major, $minor] = explode('.', $value, 2);
        } else {
            $major = $value;
            $minor = '0';
        }

        $minor = substr(str_pad($minor, 2, '0'), 0, 2);
        $cents = ((int) $major) * 100 + (int) $minor;

        return $negative ? -$cents : $cents;
    }

    public static function fromApiMoneyToCents(ApiMoney $money): int
    {
        return self::decimalToCents($money->value);
    }
}
