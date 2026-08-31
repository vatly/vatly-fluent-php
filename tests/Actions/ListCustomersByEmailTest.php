<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Actions;

use Mockery;
use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Resources\CustomerCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\ListCustomersByEmail;
use Vatly\Fluent\Tests\TestCase;

class ListCustomersByEmailTest extends TestCase
{
    public function test_it_forwards_the_email_to_the_customer_endpoint(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $collection = new CustomerCollection($apiClient, 0, null);

        $endpoint->shouldReceive('listByEmail')
            ->once()
            ->with('jane@example.test', [])
            ->andReturn($collection);

        $action = new ListCustomersByEmail($apiClient);

        $this->assertSame($collection, $action->execute('jane@example.test'));
    }
}
