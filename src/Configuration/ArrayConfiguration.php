<?php

declare(strict_types=1);

namespace Vatly\Fluent\Configuration;

use Vatly\Fluent\Concerns\DerivesTestmodeFromApiKey;
use Vatly\Fluent\Contracts\ConfigurationInterface;

/**
 * In-memory {@see ConfigurationInterface} for tests and non-framework
 * adopters that don't need a config-file backed implementation.
 *
 * Required: `api_key`. All other values fall back to sensible defaults.
 */
final class ArrayConfiguration implements ConfigurationInterface
{
    use DerivesTestmodeFromApiKey;

    /**
     * @param array{
     *     api_key: string,
     *     api_url?: string,
     *     api_version?: string,
     *     webhook_secret?: ?string,
     *     redirect_url_success?: string,
     *     redirect_url_canceled?: string,
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {
        //
    }

    public function getApiKey(): string
    {
        return $this->config['api_key'];
    }

    public function getApiUrl(): string
    {
        return $this->config['api_url'] ?? 'https://api.vatly.com';
    }

    public function getApiVersion(): string
    {
        return $this->config['api_version'] ?? 'v1';
    }

    public function getWebhookSecret(): ?string
    {
        return $this->config['webhook_secret'] ?? null;
    }

    public function getDefaultRedirectUrlSuccess(): string
    {
        return $this->config['redirect_url_success'] ?? '';
    }

    public function getDefaultRedirectUrlCanceled(): string
    {
        return $this->config['redirect_url_canceled'] ?? '';
    }
}
