<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

use Vatly\API\Resources\Checkout;

/**
 * Response from creating a checkout session.
 */
class CreateCheckoutResponse
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $checkoutUrl,
    ) {
        //
    }

    public static function fromApiResponse(Checkout $checkout): static
    {
        return new static(
            checkoutId: $checkout->id,
            checkoutUrl: $checkout->links->checkoutUrl->href,
        );
    }

    /**
     * Get the URL to redirect the user to for checkout.
     */
    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }
}
