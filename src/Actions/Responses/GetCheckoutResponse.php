<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

use Vatly\API\Resources\Checkout;

/**
 * Response from getting a checkout session.
 */
class GetCheckoutResponse
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $checkoutUrl,
        public readonly string $status,
    ) {
        //
    }

    public static function fromApiResponse(Checkout $checkout): static
    {
        return new static(
            checkoutId: $checkout->id,
            checkoutUrl: $checkout->links->checkoutUrl->href,
            status: $checkout->status ?? 'unknown',
        );
    }
}
