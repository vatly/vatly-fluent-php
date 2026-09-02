<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Resources\Customer as ApiCustomer;
use Vatly\API\Types\PortalSession;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCustomerPortalSession;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\UpdateCustomer;
use Vatly\Fluent\CustomerHandle;
use Vatly\Fluent\Data\UpdateCustomerData;

class CustomerHandleTest extends TestCase
{
    public function test_id_does_not_trigger_a_fetch(): void
    {
        $getAction = Mockery::mock(GetCustomer::class);
        $getAction->shouldNotReceive('execute');

        $handle = $this->buildHandle(getAction: $getAction);

        $this->assertSame('customer_abc', $handle->id());
    }

    public function test_accessors_lazily_fetch_and_memoize_the_resource(): void
    {
        $customer = $this->makeCustomer('customer_abc', 'Jane Doe', 'jane@example.test');

        $getAction = Mockery::mock(GetCustomer::class);
        // Fetched exactly once despite three accessor calls.
        $getAction->shouldReceive('execute')->with('customer_abc')->once()->andReturn($customer);

        $handle = $this->buildHandle(getAction: $getAction);

        $this->assertSame('Jane Doe', $handle->name());
        $this->assertSame('jane@example.test', $handle->email());
        $this->assertSame(['name' => 'Jane Doe', 'email' => 'jane@example.test'], $handle->identity());
        $this->assertSame($customer, $handle->model());
    }

    public function test_update_forwards_the_dto_and_refreshes_the_cached_resource(): void
    {
        $updated = $this->makeCustomer('customer_abc', 'New Name', 'new@example.test');

        $updateAction = Mockery::mock(UpdateCustomer::class);
        $updateAction->shouldReceive('execute')
            ->once()
            ->with('customer_abc', ['name' => 'New Name', 'email' => 'new@example.test'])
            ->andReturn($updated);

        // No GET needed: update() populates the cache from its own response.
        $getAction = Mockery::mock(GetCustomer::class);
        $getAction->shouldNotReceive('execute');

        $handle = $this->buildHandle(getAction: $getAction, updateAction: $updateAction);

        $returned = $handle->update(new UpdateCustomerData(name: 'New Name', email: 'new@example.test'));

        $this->assertSame($handle, $returned);
        $this->assertSame('New Name', $handle->name());
    }

    public function test_update_accepts_a_plain_array(): void
    {
        $updated = $this->makeCustomer('customer_abc', 'Arr Name', null);

        $updateAction = Mockery::mock(UpdateCustomer::class);
        $updateAction->shouldReceive('execute')
            ->once()
            ->with('customer_abc', ['name' => null])
            ->andReturn($updated);

        $handle = $this->buildHandle(updateAction: $updateAction);

        $handle->update(['name' => null]);
    }

    public function test_portal_session_does_not_fetch_the_customer(): void
    {
        $session = new PortalSession('https://portal.vatly.test/s/abc', '2024-01-15T10:15:00Z', 'https://app.test/back');

        $portalAction = Mockery::mock(CreateCustomerPortalSession::class);
        $portalAction->shouldReceive('execute')
            ->once()
            ->with('customer_abc', ['returnUrl' => 'https://app.test/back'])
            ->andReturn($session);

        $getAction = Mockery::mock(GetCustomer::class);
        $getAction->shouldNotReceive('execute');

        $handle = $this->buildHandle(getAction: $getAction, portalSessionAction: $portalAction);

        $result = $handle->portalSession(['returnUrl' => 'https://app.test/back']);

        $this->assertSame($session, $result);
        $this->assertSame('https://portal.vatly.test/s/abc', $result->url);
    }

    public function test_create_portal_session_is_an_alias(): void
    {
        $session = new PortalSession('https://portal.vatly.test/s/xyz', '2024-01-15T10:15:00Z');

        $portalAction = Mockery::mock(CreateCustomerPortalSession::class);
        $portalAction->shouldReceive('execute')->once()->with('customer_abc', [])->andReturn($session);

        $handle = $this->buildHandle(portalSessionAction: $portalAction);

        $this->assertSame($session, $handle->createPortalSession());
    }

    public function test_sync_refetches_the_resource(): void
    {
        $first = $this->makeCustomer('customer_abc', 'Old', 'old@example.test');
        $second = $this->makeCustomer('customer_abc', 'Fresh', 'fresh@example.test');

        $getAction = Mockery::mock(GetCustomer::class);
        $getAction->shouldReceive('execute')->with('customer_abc')->twice()->andReturn($first, $second);

        $handle = $this->buildHandle(getAction: $getAction);

        $this->assertSame('Old', $handle->name());
        $handle->sync();
        $this->assertSame('Fresh', $handle->name());
    }

    private function buildHandle(
        ?GetCustomer $getAction = null,
        ?UpdateCustomer $updateAction = null,
        ?CreateCustomerPortalSession $portalSessionAction = null,
    ): CustomerHandle {
        return new CustomerHandle(
            customerId: 'customer_abc',
            getAction: $getAction ?? Mockery::mock(GetCustomer::class),
            updateAction: $updateAction ?? Mockery::mock(UpdateCustomer::class),
            portalSessionAction: $portalSessionAction ?? Mockery::mock(CreateCustomerPortalSession::class),
        );
    }

    private function makeCustomer(string $id, ?string $name, ?string $email): ApiCustomer
    {
        $customer = new ApiCustomer(Mockery::mock(VatlyApiClient::class));
        $customer->id = $id;
        $customer->name = $name;
        $customer->email = $email;

        return $customer;
    }
}
