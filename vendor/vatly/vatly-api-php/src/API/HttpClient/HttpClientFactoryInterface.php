<?php

declare(strict_types=1);

namespace Vatly\API\HttpClient;

interface HttpClientFactoryInterface
{
    public function make(): HttpClientInterface;
}
