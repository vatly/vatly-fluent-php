<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\VatlyApiClient;

abstract class BaseAction
{
    protected VatlyApiClient $vatlyApiClient;

    public function __construct(VatlyApiClient $vatlyApiClient)
    {
        $this->vatlyApiClient = $vatlyApiClient;
    }
}
