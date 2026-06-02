<?php

declare(strict_types=1);

namespace Vatly\Fluent\Testing;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\CustomerProfile;

/**
 * Recording {@see CheckoutBuilder} handed out by {@see FakeVatly}.
 *
 * Inherits the real builder surface; only `create()` is overridden to record
 * the assembled payload on the `FakeVatly` and return the scripted Checkout
 * (no API call, no empty-items guard — a test shouldn't have to fully populate
 * a cart to assert a redirect).
 */
final class FakeCheckoutBuilder extends CheckoutBuilder
{
    public function __construct(
        private readonly FakeVatly $vatly,
        CustomerProfile $profile,
    ) {
        parent::__construct(
            customer: $profile,
            createCheckout: $vatly->createCheckout(),
        );
    }

    public function create(
        array $items,
        string $redirectUrlSuccess,
        string $redirectUrlCanceled,
        array $payloadOverrides = [],
    ): Checkout {
        $this
            ->withItems($items)
            ->withRedirectUrlSuccess($redirectUrlSuccess)
            ->withRedirectUrlCanceled($redirectUrlCanceled);

        return $this->vatly->recordCheckoutCreated($this->payload($payloadOverrides));
    }
}
