<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

/**
 * A correctly signed webhook was sent for the other mode than the configured
 * API key: a test-mode event while the key is live, or the reverse.
 *
 * The webhook is refused before anything is stored. It extends
 * {@see InvalidWebhookSignatureException}, so drivers that already catch that
 * refuse it too.
 */
final class WebhookModeMismatchException extends InvalidWebhookSignatureException
{
    public function __construct(public readonly bool $webhookTestmode)
    {
        $webhookMode = $webhookTestmode ? 'test' : 'live';
        $keyMode = $webhookTestmode ? 'live' : 'test';

        parent::__construct("This Vatly webhook is for {$webhookMode} mode, but the configured API key is for {$keyMode} mode.");
    }
}
