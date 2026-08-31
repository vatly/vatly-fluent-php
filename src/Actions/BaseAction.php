<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Concerns\GuardsApiCalls;

abstract class BaseAction
{
    use GuardsApiCalls;

    public function __construct(
        /** @readonly */
        protected VatlyApiClient $vatlyApiClient,
    ) {
        //
    }
}
