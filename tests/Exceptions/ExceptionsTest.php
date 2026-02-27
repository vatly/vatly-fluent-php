<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Exceptions;

use Exception;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Exceptions\CustomerAlreadyCreatedException;
use Vatly\Fluent\Exceptions\IncompleteInformationException;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Exceptions\VatlyException;
use Vatly\Fluent\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_vatly_exception_is_the_base_exception_class(): void
    {
        $exception = InvalidWebhookSignatureException::missingSignature();

        $this->assertInstanceOf(VatlyException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_invalid_webhook_signature_exception_extends_vatly_exception(): void
    {
        $exception = InvalidWebhookSignatureException::missingSignature();

        $this->assertInstanceOf(VatlyException::class, $exception);
    }

    public function test_missing_signature_creates_exception_with_correct_message(): void
    {
        $exception = InvalidWebhookSignatureException::missingSignature();

        $this->assertSame('Missing Vatly webhook signature.', $exception->getMessage());
    }

    public function test_invalid_signature_creates_exception_with_correct_message(): void
    {
        $exception = InvalidWebhookSignatureException::invalidSignature();

        $this->assertSame('Invalid Vatly webhook signature.', $exception->getMessage());
    }

    public function test_incomplete_information_exception_extends_vatly_exception(): void
    {
        $exception = IncompleteInformationException::noCheckoutItems();

        $this->assertInstanceOf(VatlyException::class, $exception);
    }

    public function test_no_checkout_items_creates_exception_with_correct_message(): void
    {
        $exception = IncompleteInformationException::noCheckoutItems();

        $this->assertSame('No checkout items provided. At least one item should be set when creating a checkout.', $exception->getMessage());
    }

    public function test_customer_already_created_exception_extends_vatly_exception(): void
    {
        $billable = $this->createMockBillable('vat_123');
        $exception = CustomerAlreadyCreatedException::exists($billable);

        $this->assertInstanceOf(VatlyException::class, $exception);
    }

    public function test_exists_creates_exception_with_billable_class_and_vatly_id(): void
    {
        $billable = $this->createMockBillable('vat_456');
        $exception = CustomerAlreadyCreatedException::exists($billable);

        $this->assertStringContainsString('vat_456', $exception->getMessage());
        $this->assertStringContainsString('is already a Vatly customer', $exception->getMessage());
    }

    private function createMockBillable(string $vatlyId): BillableInterface
    {
        return new class($vatlyId) implements BillableInterface {
            public function __construct(private string $vatlyId) {}

            public function getVatlyId(): string
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

            public function save(): mixed
            {
                return true;
            }
        };
    }
}
