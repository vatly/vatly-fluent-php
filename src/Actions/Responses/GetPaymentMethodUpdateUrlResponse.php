<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

/**
 * Response containing the URL for updating payment method.
 */
class GetPaymentMethodUpdateUrlResponse
{
    public function __construct(
        public readonly string $url,
        public readonly string $type,
    ) {
        //
    }
}
