<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Vatly\Fluent\Vatly;

class VatlyTest extends TestCase
{
    public function test_constructor_applies_api_endpoint_when_provided(): void
    {
        $vatly = new Vatly(
            apiKey: 'test_abcdefghijklmnopqrstuvwxyz',
            apiEndpoint: 'https://api.example.test',
        );

        $this->assertSame('https://api.example.test', $vatly->getApiClient()->getApiEndpoint());
    }

    public function test_constructor_applies_api_version_when_provided(): void
    {
        $vatly = new Vatly(
            apiKey: 'test_abcdefghijklmnopqrstuvwxyz',
            apiVersion: 'v2',
        );

        $this->assertSame('v2', $vatly->getApiClient()->getApiVersion());
    }

    public function test_constructor_leaves_endpoint_and_version_at_defaults_when_omitted(): void
    {
        $vatlyOnlyKey = new Vatly('test_abcdefghijklmnopqrstuvwxyz');
        $vatlyAllArgs = new Vatly(
            apiKey: 'test_abcdefghijklmnopqrstuvwxyz',
            apiEndpoint: null,
            apiVersion: null,
        );

        $this->assertSame(
            $vatlyOnlyKey->getApiClient()->getApiEndpoint(),
            $vatlyAllArgs->getApiClient()->getApiEndpoint(),
        );
        $this->assertSame(
            $vatlyOnlyKey->getApiClient()->getApiVersion(),
            $vatlyAllArgs->getApiClient()->getApiVersion(),
        );
    }
}
