<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Vatly\API\Types\Money;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a {@see Money} value from integer minor units (cents).
     *
     * api-php's webhook event DTOs now carry Money (decimal-string + currency)
     * rather than raw int cents; this keeps test fixtures terse while exercising
     * the round-trip that the reactions flatten back via {@see Money::toCents()}.
     */
    protected static function money(int $cents, string $currency = 'EUR'): Money
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $value = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);

        return new Money($currency, $negative ? '-' . $value : $value);
    }
}
