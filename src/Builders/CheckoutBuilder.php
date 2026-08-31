<?php

declare(strict_types=1);

namespace Vatly\Fluent\Builders;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Builders\Concerns\ManagesTestmode;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Exceptions\IncompleteInformationException;

class CheckoutBuilder
{
    use ManagesTestmode;

    protected string $redirectUrlSuccess = '';

    protected string $redirectUrlCanceled = '';

    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    protected ?string $locale = null;

    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    public function __construct(
        /** @readonly */
        protected CustomerProfile $customer,
        /** @readonly */
        protected CreateCheckout $createCheckout,
    ) {
        //
    }

    /**
     * Build the checkout payload.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function payload(array $overrides = [], bool $filtered = true): array
    {
        $payload = array_merge([
            'products' => $this->items,
            'customerId' => $this->customer->vatlyId,
            'redirectUrlSuccess' => $this->redirectUrlSuccess,
            'redirectUrlCanceled' => $this->redirectUrlCanceled,
            'testmode' => $this->testmode,
            'metadata' => $this->metadata,
            'locale' => $this->locale,
        ], $overrides);

        return $filtered ? array_filter($payload, fn ($value) => $value !== null) : $payload;
    }

    /**
     * Create the checkout session.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $payloadOverrides
     */
    public function create(
        array $items,
        string $redirectUrlSuccess,
        string $redirectUrlCanceled,
        array $payloadOverrides = [],
    ): Checkout {
        $this
            ->withTestmode($this->testmode)
            ->withItems($items)
            ->withRedirectUrlSuccess($redirectUrlSuccess)
            ->withRedirectUrlCanceled($redirectUrlCanceled);

        $payload = $this->payload(overrides: $payloadOverrides);

        if (empty($payload['products'])) {
            throw IncompleteInformationException::noCheckoutItems();
        }

        return $this->createCheckout->execute($payload);
    }

    public function withRedirectUrlSuccess(string $url): static
    {
        $this->redirectUrlSuccess = $url;

        return $this;
    }

    public function withRedirectUrlCanceled(string $url): static
    {
        $this->redirectUrlCanceled = $url;

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set the language the hosted checkout is presented in.
     *
     * Set this when you already know the customer's language — it is a better
     * signal than their browser's. Accepts a bare language code (`de`), a BCP 47
     * tag (`de-AT`), or a POSIX / ISO 15897 locale (`de_DE`); all three fold to
     * the same language. Supported languages: `en`, `de`, `fr`, `nl`, `es`, `it`,
     * `pt`, `pl`. Pass `null` (the default) to let the checkout detect the
     * language from the shopper's browser.
     */
    public function withLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function withItems(array $items): static
    {
        foreach ($items as $item) {
            $this->items[] = $item;
        }

        return $this;
    }
}
