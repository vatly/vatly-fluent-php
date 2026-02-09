<?php

declare(strict_types=1);

use Vatly\Contracts\BillableInterface;
use Vatly\Exceptions\CustomerAlreadyCreatedException;
use Vatly\Exceptions\FeatureUnavailableException;
use Vatly\Exceptions\IncompleteInformationException;
use Vatly\Exceptions\InvalidCustomerException;
use Vatly\Exceptions\InvalidWebhookSignatureException;
use Vatly\Exceptions\VatlyException;

describe('VatlyException', function () {
    test('it is an exception', function () {
        $exception = new VatlyException('Test error');

        expect($exception)->toBeInstanceOf(Exception::class)
            ->and($exception->getMessage())->toBe('Test error');
    });
});

describe('InvalidWebhookSignatureException', function () {
    test('it extends VatlyException', function () {
        $exception = InvalidWebhookSignatureException::missingSignature();

        expect($exception)->toBeInstanceOf(VatlyException::class);
    });

    test('missingSignature creates exception with correct message', function () {
        $exception = InvalidWebhookSignatureException::missingSignature();

        expect($exception->getMessage())->toBe('Missing Vatly webhook signature.');
    });

    test('invalidSignature creates exception with correct message', function () {
        $exception = InvalidWebhookSignatureException::invalidSignature();

        expect($exception->getMessage())->toBe('Invalid Vatly webhook signature.');
    });
});

describe('IncompleteInformationException', function () {
    test('it extends VatlyException', function () {
        $exception = IncompleteInformationException::noCheckoutItems();

        expect($exception)->toBeInstanceOf(VatlyException::class);
    });

    test('noCheckoutItems creates exception with correct message', function () {
        $exception = IncompleteInformationException::noCheckoutItems();

        expect($exception->getMessage())->toBe('No checkout items provided. At least one item should be set when creating a checkout.');
    });
});

describe('CustomerAlreadyCreatedException', function () {
    test('it extends VatlyException', function () {
        $billable = createMockBillable('vat_123');
        $exception = CustomerAlreadyCreatedException::exists($billable);

        expect($exception)->toBeInstanceOf(VatlyException::class);
    });

    test('exists creates exception with billable class and vatly ID', function () {
        $billable = createMockBillable('vat_456');
        $exception = CustomerAlreadyCreatedException::exists($billable);

        expect($exception->getMessage())->toContain('vat_456')
            ->and($exception->getMessage())->toContain('MockBillable');
    });
});

/**
 * Helper to create a mock billable.
 */
function createMockBillable(string $vatlyId): BillableInterface
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
            return 'MockBillable';
        }

        public function save(): void
        {
            // Mock save
        }
    };
}
