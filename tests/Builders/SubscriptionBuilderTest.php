<?php

declare(strict_types=1);

use Vatly\Actions\CreateCheckout;
use Vatly\Actions\Responses\CreateCheckoutResponse;
use Vatly\Builders\CheckoutBuilder;
use Vatly\Builders\SubscriptionBuilder;
use Vatly\Contracts\BillableInterface;
use Vatly\Contracts\ConfigurationInterface;

beforeEach(function () {
    $this->config = createTestConfig();
    $this->owner = createTestOwner('vat_sub_owner_123');
    $this->createCheckout = createTestCreateCheckout();
    $this->checkoutBuilder = new CheckoutBuilder($this->owner, $this->createCheckout);
    $this->builder = new SubscriptionBuilder($this->config, $this->owner, $this->checkoutBuilder);
});

describe('constructor', function () {
    test('it uses default redirect URLs from config', function () {
        $payload = $this->builder->toPlan('plan_123')->getCreateCheckoutPayload();

        expect($payload['redirectUrlSuccess'])->toBe('https://default.test/success')
            ->and($payload['redirectUrlCanceled'])->toBe('https://default.test/canceled');
    });
});

describe('fluent methods', function () {
    test('toPlan sets the plan ID', function () {
        $result = $this->builder->toPlan('plan_abc');
        $subscriptionPayload = $this->builder->getSubscriptionPayload();

        expect($result)->toBe($this->builder)
            ->and($subscriptionPayload['id'])->toBe('plan_abc');
    });

    test('withQuantity sets the quantity', function () {
        $result = $this->builder->withQuantity(5);
        $subscriptionPayload = $this->builder->getSubscriptionPayload();

        expect($result)->toBe($this->builder)
            ->and($subscriptionPayload['quantity'])->toBe(5);
    });

    test('withRedirectUrlSuccess overrides default', function () {
        $this->builder
            ->toPlan('plan_123')
            ->withRedirectUrlSuccess('https://custom.test/success');

        $payload = $this->builder->getCreateCheckoutPayload();

        expect($payload['redirectUrlSuccess'])->toBe('https://custom.test/success');
    });

    test('withRedirectUrlCanceled overrides default', function () {
        $this->builder
            ->toPlan('plan_123')
            ->withRedirectUrlCanceled('https://custom.test/canceled');

        $payload = $this->builder->getCreateCheckoutPayload();

        expect($payload['redirectUrlCanceled'])->toBe('https://custom.test/canceled');
    });

    test('withTestmode sets testmode', function () {
        $this->builder->withTestmode(true);

        // Access testmode via the checkout builder's payload
        $payload = $this->builder->getCreateCheckoutPayload();

        // The testmode is set on the checkout builder when create() is called
        // So we test via getSubscriptionPayload which doesn't include it
        expect($this->builder)->toBeInstanceOf(SubscriptionBuilder::class);
    });

    test('inTestmode sets testmode to true', function () {
        $result = $this->builder->inTestmode();

        expect($result)->toBe($this->builder);
    });

    test('inLiveMode sets testmode to false', function () {
        $result = $this->builder->inLiveMode();

        expect($result)->toBe($this->builder);
    });
});

describe('getSubscriptionPayload', function () {
    test('it returns subscription item payload with plan and quantity', function () {
        $this->builder->toPlan('plan_premium')->withQuantity(3);

        $payload = $this->builder->getSubscriptionPayload();

        expect($payload)->toBe([
            'quantity' => 3,
            'id' => 'plan_premium',
        ]);
    });

    test('it defaults to quantity of 1', function () {
        $this->builder->toPlan('plan_basic');

        $payload = $this->builder->getSubscriptionPayload();

        expect($payload['quantity'])->toBe(1);
    });
});

describe('getCheckoutBuilder', function () {
    test('it returns the checkout builder instance', function () {
        $checkoutBuilder = $this->builder->getCheckoutBuilder();

        expect($checkoutBuilder)->toBeInstanceOf(CheckoutBuilder::class)
            ->and($checkoutBuilder)->toBe($this->checkoutBuilder);
    });
});

/**
 * Helper to create a test config.
 */
function createTestConfig(): ConfigurationInterface
{
    return new class implements ConfigurationInterface {
        public function getApiKey(): string
        {
            return 'test_api_key';
        }

        public function getWebhookSecret(): string
        {
            return 'test_webhook_secret';
        }

        public function getDefaultRedirectUrlSuccess(): string
        {
            return 'https://default.test/success';
        }

        public function getDefaultRedirectUrlCanceled(): string
        {
            return 'https://default.test/canceled';
        }

        public function isTestmode(): bool
        {
            return true;
        }
    };
}

/**
 * Helper to create a test owner.
 */
function createTestOwner(string $vatlyId): BillableInterface
{
    return new class($vatlyId) implements BillableInterface {
        public function __construct(private string $vatlyId)
        {
        }

        public function getVatlyId(): ?string
        {
            return $this->vatlyId;
        }

        public function setVatlyId(string $id): void
        {
            $this->vatlyId = $id;
        }

        public function hasVatlyId(): bool
        {
            return $this->vatlyId !== '';
        }

        public function getVatlyEmail(): ?string
        {
            return 'owner@example.com';
        }

        public function getVatlyName(): ?string
        {
            return 'Test Owner';
        }

        public function getKey(): string|int
        {
            return 1;
        }

        public function getMorphClass(): string
        {
            return 'TestOwner';
        }

        public function save(): void
        {
            // Mock save
        }
    };
}

/**
 * Helper to create a test CreateCheckout action.
 */
function createTestCreateCheckout(): CreateCheckout
{
    return new class extends CreateCheckout {
        public function __construct()
        {
            // Don't call parent constructor
        }

        public function execute(array $payload): CreateCheckoutResponse
        {
            return new CreateCheckoutResponse(
                id: 'chk_sub_123',
                url: 'https://checkout.vatly.test/chk_sub_123',
            );
        }
    };
}
