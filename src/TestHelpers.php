<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Concerns\GuardsApiCalls;

/**
 * Fluent wrapper over Vatly's test-mode helpers.
 *
 * Reached via {@see Vatly::testHelpers()}. These operate on the Vatly API in
 * test mode only — they let a test suite drive time-based subscription flows
 * (renewals, payment recovery) without waiting for the real billing clock.
 *
 * Distinct from {@see \Vatly\Fluent\Testing\FakeVatly}: the fakes stub the
 * fluent SDK inside a consumer's own tests, whereas this class calls the real
 * Vatly sandbox to advance server-side state and provoke real webhooks.
 */
class TestHelpers
{
    use GuardsApiCalls;

    public function __construct(
        /** @readonly */
        private VatlyApiClient $apiClient,
    ) {
        //
    }

    /**
     * Fast-forward a subscription's renewal (test mode only).
     *
     * Pass a `$body` to force the outcome of the renewal payment; leave it empty
     * to advance the billing cycle and leave the payment pending. Prefer the
     * intent-named helpers below over hand-building the body.
     *
     * @param array<string, mixed> $body  e.g. `['paymentStatus' => 'failed', 'failureReason' => 'card_expired']`.
     */
    public function fastForwardRenewal(string $subscriptionId, array $body = []): ?object
    {
        return $this->guardApiCall(fn () => $this->apiClient->testHelpers->fastForwardSubscriptionRenewal($subscriptionId, $body));
    }

    /**
     * Advance the billing cycle and leave the renewal payment pending (the
     * default fast-forward behaviour).
     */
    public function advanceRenewal(string $subscriptionId): ?object
    {
        return $this->fastForwardRenewal($subscriptionId);
    }

    /**
     * Advance the billing cycle and settle the renewal payment as paid.
     */
    public function forceRenewalPaid(string $subscriptionId): ?object
    {
        return $this->fastForwardRenewal($subscriptionId, ['paymentStatus' => 'paid']);
    }

    /**
     * Advance the billing cycle and decline the renewal payment, starting a
     * payment recovery so `order.payment_failed` is delivered to your webhook
     * endpoint.
     *
     * The optional `$failureReason` picks which decline to simulate. A soft
     * decline (`insufficient_funds`, `temporary_decline`, `general_failure`)
     * retries over weeks; any other value (e.g. `card_expired`) is a hard decline
     * that drives the customer to supply a new payment method.
     */
    public function forceRenewalFailed(string $subscriptionId, ?string $failureReason = null): ?object
    {
        $body = ['paymentStatus' => 'failed'];

        if ($failureReason !== null) {
            $body['failureReason'] = $failureReason;
        }

        return $this->fastForwardRenewal($subscriptionId, $body);
    }

    /**
     * Simulate the outcome of a test order's pending payment.
     *
     * Settles or declines a payment that is already awaiting an outcome — for
     * example a subscription renewal, or a subscription update made with
     * `invoiceImmediately: true`. Unlike {@see fastForwardRenewal()}, this does
     * not advance the billing cycle. Prefer the intent-named helpers below over
     * hand-building the body.
     *
     * @param array<string, mixed> $body  e.g. `['paymentStatus' => 'paid']` or
     *                                     `['paymentStatus' => 'failed', 'failureReason' => 'card_expired']`.
     */
    public function simulateOrderPayment(string $orderId, array $body): ?object
    {
        return $this->guardApiCall(fn () => $this->apiClient->testHelpers->simulateOrderPayment($orderId, $body));
    }

    /**
     * Settle an order's pending payment, so `order.paid` is delivered to your
     * webhook endpoint.
     */
    public function forceOrderPaymentPaid(string $orderId): ?object
    {
        return $this->simulateOrderPayment($orderId, ['paymentStatus' => 'paid']);
    }

    /**
     * Decline an order's pending payment, starting a payment recovery so
     * `order.payment_failed` is delivered to your webhook endpoint.
     *
     * The optional `$failureReason` picks which decline to simulate. A soft
     * decline (`insufficient_funds`, `temporary_decline`, `general_failure`)
     * retries over weeks; any other value (e.g. `card_expired`) is a hard decline
     * that drives the customer to supply a new payment method.
     */
    public function forceOrderPaymentFailed(string $orderId, ?string $failureReason = null): ?object
    {
        $body = ['paymentStatus' => 'failed'];

        if ($failureReason !== null) {
            $body['failureReason'] = $failureReason;
        }

        return $this->simulateOrderPayment($orderId, $body);
    }
}
