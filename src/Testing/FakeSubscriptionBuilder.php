<?php

declare(strict_types=1);

namespace Vatly\Fluent\Testing;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\CustomerProfile;

/**
 * Recording {@see SubscriptionBuilder} handed out by {@see FakeVatly}.
 *
 * All the fluent setters (`toPlan`, `withQuantity`, `withRedirectUrl*`, …) are
 * inherited unchanged, so a test exercises the real builder API. Only
 * `create()` is overridden: instead of hitting the API it records the
 * subscription payload on the `FakeVatly` and returns the scripted Checkout.
 */
final class FakeSubscriptionBuilder extends SubscriptionBuilder
{
    public function __construct(
        private readonly FakeVatly $vatly,
        CustomerProfile $profile,
    ) {
        parent::__construct(
            config: $vatly->getWiring()->config,
            customer: $profile,
            checkoutBuilder: new FakeCheckoutBuilder($vatly, $profile),
        );
    }

    public function create(array $checkoutOptions = []): Checkout
    {
        return $this->vatly->recordSubscriptionCreated(
            $this->getSubscriptionPayload(),
            $this->customer,
        );
    }
}
