<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException implements VatlyException
{
    private bool $secretNotConfigured = false;

    /**
     * No webhook signing secret is configured, so every webhook is refused.
     *
     * Check {@see isSecretNotConfigured()} to answer with a "not configured"
     * response (such as HTTP 503) so Vatly retries once the secret is set.
     */
    public static function secretNotConfigured(): self
    {
        $exception = new self('No Vatly webhook signing secret is configured. Set the secret from your Vatly dashboard to receive webhooks.');
        $exception->secretNotConfigured = true;

        return $exception;
    }

    public function isSecretNotConfigured(): bool
    {
        return $this->secretNotConfigured;
    }

    public static function missingSignature(): self
    {
        return new self('Missing Vatly webhook signature.');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid Vatly webhook signature.');
    }
}
