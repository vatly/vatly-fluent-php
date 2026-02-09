<?php

declare(strict_types=1);

namespace Vatly\Builders\Concerns;

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
