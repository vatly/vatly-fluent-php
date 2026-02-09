<?php

declare(strict_types=1);

use Vatly\Exceptions\InvalidWebhookSignatureException;
use Vatly\Webhooks\SignatureVerifier;

beforeEach(function () {
    $this->verifier = new SignatureVerifier();
    $this->secret = 'test-webhook-secret';
    $this->payload = '{"eventName":"subscription.started","resourceId":"sub_123"}';
});

test('it verifies valid signature', function () {
    $validSignature = hash_hmac('sha256', $this->payload, $this->secret);

    // Should not throw
    $this->verifier->verify($validSignature, $this->payload, $this->secret);

    expect(true)->toBeTrue(); // If we get here, verification passed
});

test('it throws exception for invalid signature', function () {
    $invalidSignature = 'invalid-signature';

    $this->verifier->verify($invalidSignature, $this->payload, $this->secret);
})->throws(InvalidWebhookSignatureException::class, 'Invalid Vatly webhook signature');

test('it throws exception for missing signature', function () {
    $this->verifier->verify('', $this->payload, $this->secret);
})->throws(InvalidWebhookSignatureException::class, 'Missing Vatly webhook signature');

test('isValid returns true for valid signature', function () {
    $validSignature = hash_hmac('sha256', $this->payload, $this->secret);

    expect($this->verifier->isValid($validSignature, $this->payload, $this->secret))->toBeTrue();
});

test('isValid returns false for invalid signature', function () {
    expect($this->verifier->isValid('invalid', $this->payload, $this->secret))->toBeFalse();
});

test('isValid returns false for empty signature', function () {
    expect($this->verifier->isValid('', $this->payload, $this->secret))->toBeFalse();
});
