<?php

declare(strict_types=1);

namespace Vatly\Contracts;

/**
 * Interface for Vatly configuration.
 */
interface ConfigurationInterface
{
    /**
     * Get the API key.
     */
    public function getApiKey(): string;

    /**
     * Get the API URL.
     */
    public function getApiUrl(): string;

    /**
     * Get the API version.
     */
    public function getApiVersion(): string;

    /**
     * Get the webhook secret.
     */
    public function getWebhookSecret(): ?string;

    /**
     * Check if testmode is enabled.
     */
    public function isTestmode(): bool;

    /**
     * Get the default success redirect URL.
     */
    public function getDefaultRedirectUrlSuccess(): string;

    /**
     * Get the default cancel redirect URL.
     */
    public function getDefaultRedirectUrlCanceled(): string;

    /**
     * Get the billable model class name.
     */
    public function getBillableModel(): string;
}
