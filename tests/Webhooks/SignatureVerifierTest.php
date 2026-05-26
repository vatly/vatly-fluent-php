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
        $header = $this->makeSignatureHeader($this->payload, $this->secret);

        // Should not throw
        $this->verifier->verify($header, $this->payload, $this->secret);

        $this->assertTrue(true); // If we get here, verification passed
    }

    public function test_it_throws_exception_for_invalid_signature(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Invalid Vatly webhook signature');

        $header = 't='.time().',v1=deadbeef';

        $this->verifier->verify($header, $this->payload, $this->secret);
    }

    public function test_it_throws_exception_for_missing_signature(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Missing Vatly webhook signature');

        $this->verifier->verify('', $this->payload, $this->secret);
    }

    public function test_it_throws_exception_for_malformed_header(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);

        $this->verifier->verify('garbage', $this->payload, $this->secret);
    }

    public function test_it_throws_exception_for_stale_timestamp(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);

        $staleTimestamp = time() - 3600;
        $signature = hash_hmac('sha256', $staleTimestamp.'.'.$this->payload, $this->secret);
        $header = "t={$staleTimestamp},v1={$signature}";

        $this->verifier->verify($header, $this->payload, $this->secret);
    }

    public function test_it_throws_exception_for_tampered_payload(): void
    {
        $this->expectException(InvalidWebhookSignatureException::class);

        $header = $this->makeSignatureHeader($this->payload, $this->secret);

        $this->verifier->verify($header, $this->payload.'tampered', $this->secret);
    }

    public function test_is_valid_returns_true_for_valid_signature(): void
    {
        $header = $this->makeSignatureHeader($this->payload, $this->secret);

        $this->assertTrue($this->verifier->isValid($header, $this->payload, $this->secret));
    }

    public function test_is_valid_returns_false_for_invalid_signature(): void
    {
        $header = 't='.time().',v1=deadbeef';

        $this->assertFalse($this->verifier->isValid($header, $this->payload, $this->secret));
    }

    public function test_is_valid_returns_false_for_empty_signature(): void
    {
        $this->assertFalse($this->verifier->isValid('', $this->payload, $this->secret));
    }

    public function test_it_tolerates_unknown_future_scheme_keys(): void
    {
        $timestamp = time();
        $v1 = hash_hmac('sha256', $timestamp.'.'.$this->payload, $this->secret);
        $header = "t={$timestamp},v1={$v1},v2=somethingfromthefuture";

        $this->verifier->verify($header, $this->payload, $this->secret);

        $this->assertTrue(true);
    }

    private function makeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
