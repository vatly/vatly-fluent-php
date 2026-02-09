<?php

declare(strict_types=1);

namespace Vatly\API\Webhooks;

use Vatly\API\Exceptions\InvalidSignatureException;

class WebhookSignatureValidator
{
    protected string $webhookSecret;

    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
    }

    /**
     * Verify the webhook signature.
     *
     * @throws InvalidSignatureException
     */
    public function verify(string $payload, string $signature): void
    {
        $expectedSignature = $this->calculateSignature($payload);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid webhook signature');
        }
    }

    /**
     * Check if the signature is valid without throwing an exception.
     */
    public function isValid(string $payload, string $signature): bool
    {
        $expectedSignature = $this->calculateSignature($payload);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Calculate the expected signature for a payload.
     */
    public function calculateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }
}
