<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Actions;

use Mockery;
use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Types\PortalSession;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCustomerPortalSession;
use Vatly\Fluent\Exceptions\ApiCallFailedException;
use Vatly\Fluent\Tests\TestCase;

class CreateCustomerPortalSessionTest extends TestCase
{
    public function test_it_forwards_the_id_and_body_to_the_endpoint(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $session = new PortalSession('https://portal.vatly.test/s/1', '2024-01-15T10:15:00Z', 'https://app.test/back');

        $endpoint->shouldReceive('createPortalSession')
            ->once()
            ->with('customer_abc', ['returnUrl' => 'https://app.test/back'])
            ->andReturn($session);

        $action = new CreateCustomerPortalSession($apiClient);

        $this->assertSame($session, $action->execute('customer_abc', ['returnUrl' => 'https://app.test/back']));
    }

    public function test_it_defaults_to_an_empty_body(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $session = new PortalSession('https://portal.vatly.test/s/2', '2024-01-15T10:15:00Z');

        $endpoint->shouldReceive('createPortalSession')->once()->with('customer_abc', [])->andReturn($session);

        $action = new CreateCustomerPortalSession($apiClient);

        $this->assertSame($session, $action->execute('customer_abc'));
    }

    public function test_it_wraps_api_exceptions_under_the_vatly_marker(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $endpoint->shouldReceive('createPortalSession')
            ->once()
            ->andThrow(new ApiException('Error 404 executing API call', 404));

        $action = new CreateCustomerPortalSession($apiClient);

        $this->expectException(ApiCallFailedException::class);
        $this->expectExceptionCode(404);

        $action->execute('customer_abc');
    }
}
