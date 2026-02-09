<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\API\VatlyApiClient;

abstract class BaseAction
{
    public function __construct(
        protected readonly VatlyApiClient $vatlyApiClient,
    ) {
        //
    }
}
