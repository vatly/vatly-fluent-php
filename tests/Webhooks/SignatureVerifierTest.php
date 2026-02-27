<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Tests\TestCase;

class SignatureVerifierTest extends TestCase
{
    private SignatureVerifier $verifier;
    private string $secret;
    private string $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new SignatureVerifier();
        $this->secret = 'test-webhook-secret';
        $this->payload = '{"eventName":"subscription.started","resourceId":"sub_123"}';
    }

    public function test_it_verifies_valid_signature(): void
    {
        $validSignature = hash_hmac('sha256', $this->payload, $this->secret);

        // Should not throw
        $this->verifier->verify($validSignature, $this->payload, $this->secret);

        $this->assertTrue(true); // If we get here, verification passed
    }

    public function test_it_throws_exception_for_invalid_signature(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Invalid Vatly webhook signature');

        $this->verifier->verify('invalid-signature', $this->payload, $this->secret);
    }

    public function test_it_throws_exception_for_missing_signature(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Missing Vatly webhook signature');

        $this->verifier->verify('', $this->payload, $this->secret);
    }

    public function test_is_valid_returns_true_for_valid_signature(): void
    {
        $validSignature = hash_hmac('sha256', $this->payload, $this->secret);

        $this->assertTrue($this->verifier->isValid($validSignature, $this->payload, $this->secret));
    }

    public function test_is_valid_returns_false_for_invalid_signature(): void
    {
        $this->assertFalse($this->verifier->isValid('invalid', $this->payload, $this->secret));
    }

    public function test_is_valid_returns_false_for_empty_signature(): void
    {
        $this->assertFalse($this->verifier->isValid('', $this->payload, $this->secret));
    }
}
