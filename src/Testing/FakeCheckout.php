<?php

declare(strict_types=1);

namespace Vatly\Fluent\Testing;

use Vatly\API\Resources\Checkout;
use Vatly\API\Resources\Links\CheckoutLinks;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;

/**
 * Factory for a minimal, working {@see Checkout} resource for use in consumer
 * tests — the one with a real `links->checkoutUrl->href`, so a controller that
 * redirects to the checkout URL can be asserted against without hand-rolling
 * the full resource graph.
 *
 * ```php
 * $checkout = FakeCheckout::make('https://checkout.vatly.test/chk_123');
 * $this->get('/subscribe/plan_pro')->assertRedirect($checkout->links->checkoutUrl->href);
 * ```
 */
final class FakeCheckout
{
    /**
     * @param array<string, mixed> $overrides Resource property overrides (e.g. `status`, `customerId`).
     */
    public static function make(string $url = 'https://checkout.vatly.test/chk_fake', array $overrides = []): Checkout
    {
        $checkout = new Checkout(new VatlyApiClient());
        $checkout->id = $overrides['id'] ?? 'chk_fake';
        $checkout->resource = 'checkout';
        $checkout->status = $overrides['status'] ?? 'created';
        $checkout->testmode = $overrides['testmode'] ?? true;
        $checkout->customerId = $overrides['customerId'] ?? null;
        $checkout->redirectUrlSuccess = $overrides['redirectUrlSuccess'] ?? 'https://app.vatly.test/success';
        $checkout->redirectUrlCanceled = $overrides['redirectUrlCanceled'] ?? 'https://app.vatly.test/canceled';

        $checkout->links = new CheckoutLinks();
        $checkout->links->checkoutUrl = new Link($url, 'text/html');

        foreach ($overrides as $key => $value) {
            if (property_exists($checkout, $key)) {
                $checkout->{$key} = $value;
            }
        }

        return $checkout;
    }
}
