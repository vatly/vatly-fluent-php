<?php

declare(strict_types=1);

use Vatly\Actions\CreateCheckout;
use Vatly\Actions\Responses\CreateCheckoutResponse;
use Vatly\Builders\CheckoutBuilder;
use Vatly\Contracts\BillableInterface;
use Vatly\Exceptions\IncompleteInformationException;

beforeEach(function () {
    $this->owner = createTestBillable('vat_owner_123');
    $this->createCheckout = createMockCreateCheckout();
    $this->builder = new CheckoutBuilder($this->owner, $this->createCheckout);
});

describe('payload', function () {
    test('it builds payload with owner vatly id', function () {
        $payload = $this->builder->payload();

        expect($payload['customerId'])->toBe('vat_owner_123');
    });

    test('it includes items in payload', function () {
        $this->builder->withItems([
            ['id' => 'item_1', 'quantity' => 2],
            ['id' => 'item_2', 'quantity' => 1],
        ]);

        $payload = $this->builder->payload();

        expect($payload['products'])->toHaveCount(2)
            ->and($payload['products'][0]['id'])->toBe('item_1')
            ->and($payload['products'][1]['id'])->toBe('item_2');
    });

    test('it includes redirect URLs in payload', function () {
        $this->builder
            ->withRedirectUrlSuccess('https://example.com/success')
            ->withRedirectUrlCanceled('https://example.com/canceled');

        $payload = $this->builder->payload();

        expect($payload['redirectUrlSuccess'])->toBe('https://example.com/success')
            ->and($payload['redirectUrlCanceled'])->toBe('https://example.com/canceled');
    });

    test('it includes metadata in payload', function () {
        $this->builder->withMetadata(['order_id' => '12345']);

        $payload = $this->builder->payload();

        expect($payload['metadata'])->toBe(['order_id' => '12345']);
    });

    test('it includes testmode in payload', function () {
        $this->builder->withTestmode(true);
        $payload = $this->builder->payload();

        expect($payload['testmode'])->toBeTrue();
    });

    test('it filters null values by default', function () {
        $payload = $this->builder->payload();

        expect($payload)->not->toHaveKey('metadata');
    });

    test('it can include null values when filtered is false', function () {
        $payload = $this->builder->payload(filtered: false);

        expect($payload)->toHaveKey('metadata')
            ->and($payload['metadata'])->toBeNull();
    });

    test('it merges overrides', function () {
        $payload = $this->builder->payload(['extra' => 'value']);

        expect($payload['extra'])->toBe('value');
    });
});

describe('fluent methods', function () {
    test('withRedirectUrlSuccess returns builder instance', function () {
        $result = $this->builder->withRedirectUrlSuccess('https://example.com/success');

        expect($result)->toBe($this->builder);
    });

    test('withRedirectUrlCanceled returns builder instance', function () {
        $result = $this->builder->withRedirectUrlCanceled('https://example.com/canceled');

        expect($result)->toBe($this->builder);
    });

    test('withMetadata returns builder instance', function () {
        $result = $this->builder->withMetadata(['key' => 'value']);

        expect($result)->toBe($this->builder);
    });

    test('withItems returns builder instance', function () {
        $result = $this->builder->withItems([['id' => 'item_1', 'quantity' => 1]]);

        expect($result)->toBe($this->builder);
    });

    test('withTestmode returns builder instance', function () {
        $result = $this->builder->withTestmode(true);

        expect($result)->toBe($this->builder);
    });

    test('inTestmode sets testmode to true', function () {
        $this->builder->inTestmode();
        $payload = $this->builder->payload();

        expect($payload['testmode'])->toBeTrue();
    });

    test('inLiveMode sets testmode to false', function () {
        $this->builder->inTestmode()->inLiveMode();
        $payload = $this->builder->payload();

        expect($payload['testmode'])->toBeFalse();
    });
});

describe('create', function () {
    test('it throws exception when no items provided', function () {
        $this->builder->create(
            items: [],
            redirectUrlSuccess: 'https://example.com/success',
            redirectUrlCanceled: 'https://example.com/canceled',
        );
    })->throws(IncompleteInformationException::class, 'No checkout items provided');
});

/**
 * Helper to create a test billable.
 */
function createTestBillable(string $vatlyId): BillableInterface
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
            return 'test@example.com';
        }

        public function getVatlyName(): ?string
        {
            return 'Test User';
        }

        public function getKey(): string|int
        {
            return 1;
        }

        public function getMorphClass(): string
        {
            return 'TestBillable';
        }

        public function save(): void
        {
            // Mock save
        }
    };
}

/**
 * Helper to create a mock CreateCheckout action.
 */
function createMockCreateCheckout(): CreateCheckout
{
    return new class extends CreateCheckout {
        public function __construct()
        {
            // Don't call parent constructor - we don't need the API client for tests
        }

        public function execute(array $payload): CreateCheckoutResponse
        {
            return new CreateCheckoutResponse(
                id: 'chk_test_123',
                url: 'https://checkout.vatly.test/chk_test_123',
            );
        }
    };
}
