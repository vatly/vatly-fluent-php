<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\Exceptions\InvalidSignatureException;
use Vatly\API\Webhooks\WebhookSignatureValidator;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;

/**
 * Verify the structured `Vatly-Signature` header on incoming webhook
 * deliveries.
 *
 * The header value has the form `t=<unix_seconds>,v1=<hex_hmac_sha256>`
 * where the HMAC input is `"{$t}.{$rawBody}"`. Delegates to the upstream
 * {@see WebhookSignatureValidator} and rethrows its exception as the
 * framework-agnostic {@see InvalidWebhookSignatureException} that drivers
 * (Laravel, etc.) already catch.
 */
class SignatureVerifier
{
    /**
     * Verify the webhook signature.
     *
     * @param  string  $signatureHeader  Full value of the `Vatly-Signature` header.
     * @param  string  $payload          Raw request body bytes, exactly as received.
     * @param  string  $secret           Webhook signing secret.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function verify(string $signatureHeader, string $payload, string $secret): void
    {
        if ($signatureHeader === '') {
            throw InvalidWebhookSignatureException::missingSignature();
        }

        try {
            (new WebhookSignatureValidator($secret))->verify($payload, $signatureHeader);
        } catch (InvalidSignatureException) {
            throw InvalidWebhookSignatureException::invalidSignature();
        }
    }

    /**
     * Check if the signature is valid (returns boolean instead of throwing).
     */
    public function isValid(string $signatureHeader, string $payload, string $secret): bool
    {
        try {
            $this->verify($signatureHeader, $payload, $secret);
        } catch (InvalidWebhookSignatureException) {
            return false;
        }

        return true;
    }
}
