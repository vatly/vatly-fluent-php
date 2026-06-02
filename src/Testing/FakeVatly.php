<?php

declare(strict_types=1);

namespace Vatly\Fluent\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\SubscriptionHandle;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

/**
 * A drop-in {@see Vatly} double for consumer feature tests.
 *
 * Instead of hand-rolling a Mockery stub for every fluent entry point (which
 * breaks the moment fluent grows a method), bind a `FakeVatly` and script only
 * the bits your test cares about:
 *
 * ```php
 * $fake = (new FakeVatly())->onSubscriptionCreate(
 *     fn (string $planId) => FakeCheckout::make('https://checkout.vatly.test/chk_1'),
 * );
 * $this->app->instance(Vatly::class, $fake);
 *
 * $this->get('/vatly/subscription-checkout/plan_pro')
 *     ->assertRedirect('https://checkout.vatly.test/chk_1');
 *
 * $fake->assertSubscriptionCreated('plan_pro');
 * $fake->assertNothingCanceled();
 * ```
 *
 * It records every checkout/subscription creation, plan swap, and cancellation
 * routed through the builders/handles it hands out, and ships assertion helpers
 * in the spirit of `Notification::assertSentTo`.
 *
 * Ships in-package (like Cashier's test helpers). The PHPUnit `Assert`
 * dependency is only touched from the `assert*` methods, so production code
 * paths never load it.
 */
class FakeVatly extends Vatly
{
    /** @var array<int, array{planId: string, quantity: int, customerId: string}> */
    private array $createdSubscriptions = [];

    /** @var array<int, array<string, mixed>> */
    private array $createdCheckouts = [];

    /** @var array<int, array{from: string, to: string}> */
    private array $swaps = [];

    /** @var array<int, string> */
    private array $cancellations = [];

    private ?Closure $subscriptionCreateHandler = null;

    private ?Closure $checkoutCreateHandler = null;

    private Checkout $defaultCheckout;

    public function __construct()
    {
        parent::__construct(new Wiring(
            // Format-valid placeholder (test_ + >=18 chars) so the underlying
            // api client accepts it; the fake never makes a real API call.
            config: new ArrayConfiguration(['api_key' => 'test_fakefakefakefakefake']),
        ));

        $this->defaultCheckout = FakeCheckout::make();
    }

    // --- Scripting ---

    /**
     * Script the Checkout returned when a subscription is created. The closure
     * receives the plan id (and the full subscription payload as a 2nd arg).
     */
    public function onSubscriptionCreate(callable $handler): static
    {
        $this->subscriptionCreateHandler = Closure::fromCallable($handler);

        return $this;
    }

    /**
     * Script the Checkout returned when a checkout is created directly. The
     * closure receives the checkout payload array.
     */
    public function onCheckoutCreate(callable $handler): static
    {
        $this->checkoutCreateHandler = Closure::fromCallable($handler);

        return $this;
    }

    /**
     * Set the Checkout returned when no per-call handler is scripted.
     */
    public function withDefaultCheckout(Checkout $checkout): static
    {
        $this->defaultCheckout = $checkout;

        return $this;
    }

    // --- Overridden entry points (hand out recording fakes) ---

    public function subscriptionBuilder(CustomerProfile $profile): \Vatly\Fluent\Builders\SubscriptionBuilder
    {
        return new FakeSubscriptionBuilder($this, $profile);
    }

    public function checkoutBuilder(CustomerProfile $profile): \Vatly\Fluent\Builders\CheckoutBuilder
    {
        return new FakeCheckoutBuilder($this, $profile);
    }

    public function subscription(SubscriptionInterface $subscription): SubscriptionHandle
    {
        return new FakeSubscriptionHandle($subscription, $this);
    }

    // --- Recording hooks (called by the fakes above) ---

    /**
     * @param array<string, mixed> $payload
     *
     * @internal
     */
    public function recordSubscriptionCreated(array $payload, CustomerProfile $profile): Checkout
    {
        $planId = (string) ($payload['id'] ?? '');

        $this->createdSubscriptions[] = [
            'planId' => $planId,
            'quantity' => (int) ($payload['quantity'] ?? 1),
            'customerId' => $profile->vatlyId,
        ];

        if ($this->subscriptionCreateHandler !== null) {
            return ($this->subscriptionCreateHandler)($planId, $payload);
        }

        return $this->defaultCheckout;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @internal
     */
    public function recordCheckoutCreated(array $payload): Checkout
    {
        $this->createdCheckouts[] = $payload;

        if ($this->checkoutCreateHandler !== null) {
            return ($this->checkoutCreateHandler)($payload);
        }

        return $this->defaultCheckout;
    }

    /** @internal */
    public function recordSwap(string $from, string $to): void
    {
        $this->swaps[] = ['from' => $from, 'to' => $to];
    }

    /** @internal */
    public function recordCancellation(string $vatlyId): void
    {
        $this->cancellations[] = $vatlyId;
    }

    // --- Assertions ---

    public function assertSubscriptionCreated(?string $planId = null): void
    {
        if ($planId === null) {
            Assert::assertNotEmpty($this->createdSubscriptions, 'Expected a subscription to be created, but none were.');

            return;
        }

        $plans = array_column($this->createdSubscriptions, 'planId');
        Assert::assertContains($planId, $plans, "Expected a subscription created for plan [{$planId}], got: " . implode(', ', $plans));
    }

    public function assertCheckoutCreated(?string $productId = null): void
    {
        if ($productId === null) {
            Assert::assertNotEmpty($this->createdCheckouts, 'Expected a checkout to be created, but none were.');

            return;
        }

        $found = false;
        foreach ($this->createdCheckouts as $payload) {
            foreach ($payload['products'] ?? [] as $product) {
                if (($product['id'] ?? null) === $productId) {
                    $found = true;
                }
            }
        }

        Assert::assertTrue($found, "Expected a checkout created containing product [{$productId}].");
    }

    public function assertSubscriptionSwapped(?string $from = null, ?string $to = null): void
    {
        if ($from === null && $to === null) {
            Assert::assertNotEmpty($this->swaps, 'Expected a subscription plan swap, but none were recorded.');

            return;
        }

        $match = array_filter(
            $this->swaps,
            fn (array $swap) => ($from === null || $swap['from'] === $from)
                && ($to === null || $swap['to'] === $to),
        );

        Assert::assertNotEmpty($match, 'Expected a matching subscription plan swap, but none were recorded.');
    }

    public function assertSubscriptionCanceled(?string $vatlyId = null): void
    {
        if ($vatlyId === null) {
            Assert::assertNotEmpty($this->cancellations, 'Expected a subscription cancellation, but none were recorded.');

            return;
        }

        Assert::assertContains($vatlyId, $this->cancellations, "Expected subscription [{$vatlyId}] to be canceled.");
    }

    public function assertNothingCanceled(): void
    {
        Assert::assertEmpty($this->cancellations, 'Expected no cancellations, but some were recorded.');
    }

    public function assertNothingCreated(): void
    {
        Assert::assertEmpty($this->createdSubscriptions, 'Expected no subscriptions created.');
        Assert::assertEmpty($this->createdCheckouts, 'Expected no checkouts created.');
    }
}
