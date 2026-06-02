<?php

declare(strict_types=1);

namespace Vatly\Fluent\Builders;

use DateTimeInterface;
use InvalidArgumentException;
use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Builders\Concerns\ManagesTestmode;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\CustomerProfile;

class SubscriptionBuilder
{
    use ManagesTestmode;

    protected int $quantity = 1;

    protected string $planId = '';

    protected string $redirectUrlSuccess = '';

    protected string $redirectUrlCanceled = '';

    protected ?int $trialDays = null;

    public function __construct(
        /** @readonly */
        protected ConfigurationInterface $config,
        /** @readonly */
        protected CustomerProfile $customer,
        /** @readonly */
        protected CheckoutBuilder $checkoutBuilder,
    ) {
        $this->redirectUrlSuccess = $this->config->getDefaultRedirectUrlSuccess();
        $this->redirectUrlCanceled = $this->config->getDefaultRedirectUrlCanceled();
    }

    public function toPlan(string $planId): static
    {
        $this->planId = $planId;

        return $this;
    }

    public function withRedirectUrlSuccess(string $redirectUrlSuccess): static
    {
        $this->redirectUrlSuccess = $redirectUrlSuccess;

        return $this;
    }

    public function withRedirectUrlCanceled(string $redirectUrlCanceled): static
    {
        $this->redirectUrlCanceled = $redirectUrlCanceled;

        return $this;
    }

    public function withQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Start the subscription with a free trial of the given whole-day length.
     *
     * Vatly bills the first cycle after the trial elapses. The trial window is
     * measured in whole days from checkout creation, matching the API's
     * per-product `trialDays` input.
     *
     * @throws InvalidArgumentException When $days is not a positive integer.
     */
    public function withTrialDays(int $days): static
    {
        if ($days < 1) {
            throw new InvalidArgumentException(
                "Trial length must be at least 1 day, got {$days}.",
            );
        }

        $this->trialDays = $days;

        return $this;
    }

    /**
     * Start the subscription with a trial that ends at the given moment.
     *
     * Convenience over {@see self::withTrialDays()} for callers that think in
     * end-dates (e.g. "trial until the 1st of next month"). Because Vatly's
     * trial input is whole-day granular, the remaining time is rounded *up* to
     * the next whole day so the trial never ends earlier than requested.
     *
     * @throws InvalidArgumentException When $endsAt is not in the future.
     */
    public function withTrialEndsAt(DateTimeInterface $endsAt): static
    {
        $secondsUntil = $endsAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();

        if ($secondsUntil <= 0) {
            throw new InvalidArgumentException(
                'Trial end date must be in the future.',
            );
        }

        return $this->withTrialDays((int) ceil($secondsUntil / 86400));
    }

    /**
     * Create the subscription checkout session.
     *
     * @param array<string, mixed> $checkoutOptions
     */
    public function create(array $checkoutOptions = []): Checkout
    {
        return $this
            ->checkoutBuilder
            ->withTestmode($this->testmode)
            ->create(
                items: [$this->getSubscriptionPayload()],
                redirectUrlSuccess: $this->redirectUrlSuccess,
                redirectUrlCanceled: $this->redirectUrlCanceled,
                payloadOverrides: $checkoutOptions,
            );
    }

    /**
     * Get the subscription item payload.
     *
     * @return array<string, mixed>
     */
    public function getSubscriptionPayload(): array
    {
        $payload = [
            'quantity' => $this->quantity,
            'id' => $this->planId,
        ];

        // Only include the trial when one was set, so a plain subscription
        // payload stays minimal (and the API applies any plan-level default).
        if ($this->trialDays !== null) {
            $payload['trialDays'] = $this->trialDays;
        }

        return $payload;
    }

    /**
     * Get the full checkout payload.
     *
     * @return array<string, mixed>
     */
    public function getCreateCheckoutPayload(): array
    {
        return $this->checkoutBuilder->payload();
    }

    public function getCheckoutBuilder(): CheckoutBuilder
    {
        return $this->checkoutBuilder;
    }
}
