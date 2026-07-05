<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Actions;

use Mockery;
use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Resources\Customer;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\UpdateCustomer;
use Vatly\Fluent\Tests\TestCase;

class UpdateCustomerTest extends TestCase
{
    public function test_it_forwards_identity_fields_to_the_customer_endpoint(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $returned = new Customer($apiClient);
        $returned->id = 'cus_abc';
        $returned->name = 'Jane Doe';
        $returned->email = 'jane@example.test';

        $endpoint->shouldReceive('update')
            ->once()
            ->with('cus_abc', ['name' => 'Jane Doe', 'email' => 'jane@example.test'], [])
            ->andReturn($returned);

        $action = new UpdateCustomer($apiClient);

        $customer = $action->execute('cus_abc', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
        ]);

        $this->assertSame($returned, $customer);
        $this->assertSame('Jane Doe', $customer->name);
    }

    public function test_it_can_clear_the_name_with_null(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        $returned = new Customer($apiClient);
        $returned->id = 'cus_abc';
        $returned->name = null;

        $endpoint->shouldReceive('update')
            ->once()
            ->with('cus_abc', ['name' => null], [])
            ->andReturn($returned);

        $action = new UpdateCustomer($apiClient);

        $customer = $action->execute('cus_abc', ['name' => null]);

        $this->assertNull($customer->name);
    }
}
