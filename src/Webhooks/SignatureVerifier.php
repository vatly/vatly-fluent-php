<?php

declare(strict_types=1);

namespace Vatly\Webhooks;

use Vatly\Exceptions\InvalidWebhookSignatureException;

class SignatureVerifier
{
    /**
     * Verify the webhook signature.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function verify(string $signature, string $payload, string $secret): void
    {
        if (empty($signature)) {
            throw InvalidWebhookSignatureException::missingSignature();
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw InvalidWebhookSignatureException::invalidSignature();
        }
    }

    /**
     * Check if the signature is valid (returns boolean instead of throwing).
     */
    public function isValid(string $signature, string $payload, string $secret): bool
    {
        if (empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
