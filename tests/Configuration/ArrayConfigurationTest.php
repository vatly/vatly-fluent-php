<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Configuration;

use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Tests\TestCase;

class ArrayConfigurationTest extends TestCase
{
    public function test_returns_provided_values(): void
    {
        $config = new ArrayConfiguration([
            'api_key' => 'test_abc',
            'api_url' => 'https://api.example.test',
            'api_version' => 'v2',
            'webhook_secret' => 'whsec_xyz',
            'redirect_url_success' => 'https://app.test/success',
            'redirect_url_canceled' => 'https://app.test/canceled',
        ]);

        $this->assertSame('test_abc', $config->getApiKey());
        $this->assertSame('https://api.example.test', $config->getApiUrl());
        $this->assertSame('v2', $config->getApiVersion());
        $this->assertSame('whsec_xyz', $config->getWebhookSecret());
        $this->assertSame('https://app.test/success', $config->getDefaultRedirectUrlSuccess());
        $this->assertSame('https://app.test/canceled', $config->getDefaultRedirectUrlCanceled());
    }

    public function test_applies_sensible_defaults_when_omitted(): void
    {
        $config = new ArrayConfiguration(['api_key' => 'test_abc']);

        $this->assertSame('https://api.vatly.com', $config->getApiUrl());
        $this->assertSame('v1', $config->getApiVersion());
        $this->assertNull($config->getWebhookSecret());
        $this->assertSame('', $config->getDefaultRedirectUrlSuccess());
        $this->assertSame('', $config->getDefaultRedirectUrlCanceled());
    }

    public function test_testmode_is_true_for_test_prefix_keys(): void
    {
        $config = new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']);

        $this->assertTrue($config->isTestmode());
    }

    public function test_testmode_is_false_for_live_keys(): void
    {
        $config = new ArrayConfiguration(['api_key' => 'live_abcdefghijklmnopqrstuvwxyz']);

        $this->assertFalse($config->isTestmode());
    }
}
