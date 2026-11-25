<?php

declare(strict_types=1);

namespace Vatly\Fluent\Builders\Concerns;

/**
 * Vatly takes test mode from the API key, so this flag is not sent with the
 * checkout request. Use a `test_` key to create test-mode checkouts.
 */
trait ManagesTestmode
{
    protected bool $testmode = false;

    public function withTestmode(bool $testmode): static
    {
        $this->testmode = $testmode;

        return $this;
    }

    public function inTestmode(): static
    {
        return $this->withTestmode(true);
    }

    public function inLiveMode(): static
    {
        return $this->withTestmode(false);
    }
}
