<?php

declare(strict_types=1);

namespace Vatly\API\HttpClient;

interface HttpClientInterface
{
    public function send(
        string $httpMethod,
        string $url,
        array $headers,
        ?string $httpBody
    ): ?object;

    /**
     * The version number for the underlying http client, if available.
     * @example Guzzle/6.3
     *
     * @return string
     */
    public function versionString(): string;

    /**
     * Whether this http client provides a debugging mode. If debugging mode is enabled, the
     * request will be included in the ApiException.
     *
     * @return bool
     */
    public function supportsDebugging(): bool;
    public function enableDebugging(): void;
    public function disableDebugging(): void;
}
