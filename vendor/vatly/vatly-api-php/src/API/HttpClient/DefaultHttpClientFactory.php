<?php

declare(strict_types=1);

namespace Vatly\API\HttpClient;

class DefaultHttpClientFactory implements HttpClientFactoryInterface
{
    public function make(): HttpClientInterface
    {
        return new CurlHttpClient;
    }
}
