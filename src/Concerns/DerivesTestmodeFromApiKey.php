<?php

declare(strict_types=1);

namespace Vatly\Fluent\Concerns;

/**
 * Default implementation of {@see \Vatly\Fluent\Contracts\ConfigurationInterface::isTestmode()}.
 *
 * Testmode is inferred from the API key prefix: keys starting with `test_`
 * indicate testmode. A configuration impl that wants different semantics
 * (e.g. an explicit `VATLY_TESTMODE` env) can simply implement `isTestmode()`
 * itself instead of using this trait.
 *
 * Requires the using class to expose `getApiKey(): string`, which the
 * interface already mandates.
 */
trait DerivesTestmodeFromApiKey
{
    public function isTestmode(): bool
    {
        return str_starts_with($this->getApiKey(), 'test_');
    }
}
