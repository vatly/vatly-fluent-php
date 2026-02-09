<?php

declare(strict_types=1);

namespace Vatly\Builders;

use Vatly\Actions\Responses\CreateCheckoutResponse;
use Vatly\Builders\Concerns\ManagesTestmode;
use Vatly\Contracts\BillableInterface;
use Vatly\Contracts\ConfigurationInterface;

class SubscriptionBuilder
{
    use ManagesTestmode;

    protected int $quantity = 1;

    protected string $planId = '';

    protected string $redirectUrlSuccess = '';

    protected string $redirectUrlCanceled = '';

    public function __construct(
        protected readonly ConfigurationInterface $config,
        protected BillableInterface $owner,
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
     * Create the subscription checkout session.
     *
     * @param array<string, mixed> $checkoutOptions
     */
    public function create(array $checkoutOptions = []): CreateCheckoutResponse
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
        return [
            'quantity' => $this->quantity,
            'id' => $this->planId,
        ];
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
