<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Exceptions;

use Exception;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;
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

    public function test_customer_already_bound_on_create_extends_vatly_exception(): void
    {
        $exception = CustomerAlreadyBoundException::onCreate('host_123', 'cus_abc');

        $this->assertInstanceOf(VatlyException::class, $exception);
        $this->assertSame('host_123', $exception->hostCustomerId);
        $this->assertSame('cus_abc', $exception->existingVatlyCustomerId);
        $this->assertNull($exception->attemptedVatlyCustomerId);
    }

    public function test_customer_already_bound_on_create_message_includes_host_and_existing_ids(): void
    {
        $exception = CustomerAlreadyBoundException::onCreate('host_456', 'cus_def');

        $this->assertStringContainsString('host_456', $exception->getMessage());
        $this->assertStringContainsString('cus_def', $exception->getMessage());
    }

    public function test_customer_already_bound_on_attribute_carries_attempted_and_existing_ids(): void
    {
        $exception = CustomerAlreadyBoundException::onAttribute('host_1', 'cus_new', 'cus_existing');

        $this->assertSame('host_1', $exception->hostCustomerId);
        $this->assertSame('cus_new', $exception->attemptedVatlyCustomerId);
        $this->assertSame('cus_existing', $exception->existingVatlyCustomerId);
        $this->assertStringContainsString('cus_new', $exception->getMessage());
        $this->assertStringContainsString('cus_existing', $exception->getMessage());
        $this->assertStringContainsString('host_1', $exception->getMessage());
    }
}
