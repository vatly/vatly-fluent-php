<?php

declare(strict_types=1);

namespace Vatly\Builders;

use Vatly\Actions\CreateCheckout;
use Vatly\Actions\Responses\CreateCheckoutResponse;
use Vatly\Builders\Concerns\ManagesTestmode;
use Vatly\Contracts\BillableInterface;
use Vatly\Exceptions\IncompleteInformationException;

class CheckoutBuilder
{
    use ManagesTestmode;

    protected string $redirectUrlSuccess = '';

    protected string $redirectUrlCanceled = '';

    protected ?array $metadata = null;

    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    public function __construct(
        protected BillableInterface $owner,
        protected readonly CreateCheckout $createCheckout,
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
            'customerId' => $this->owner->getVatlyId(),
            'redirectUrlSuccess' => $this->redirectUrlSuccess,
            'redirectUrlCanceled' => $this->redirectUrlCanceled,
            'testmode' => $this->testmode,
            'metadata' => $this->metadata,
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
    ): CreateCheckoutResponse {
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
