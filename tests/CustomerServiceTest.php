<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Resources\Customer as ApiCustomer;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\CustomerService;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;

class CustomerServiceTest extends TestCase
{
    public function test_create_for_creates_customer_and_binds_to_host(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_new');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        $createCustomer->shouldReceive('execute')
            ->once()
            ->with(['email' => 'host@example.test', 'name' => 'Host Name'])
            ->andReturn($apiCustomer);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_1')->once()->andReturnNull();
        $bindings->shouldReceive('bind')->with('cus_new', 'host_1')->once();

        $customers = new CustomerService($createCustomer, Mockery::mock(GetCustomer::class), $bindings);
        $profile = new CustomerProfile(email: 'host@example.test', name: 'Host Name');

        $result = $customers->createFor('host_1', $profile);

        $this->assertSame($apiCustomer, $result);
    }

    public function test_create_for_throws_when_host_is_already_bound(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_1')->once()->andReturn('cus_existing');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        $createCustomer->shouldNotReceive('execute');

        $customers = new CustomerService($createCustomer, Mockery::mock(GetCustomer::class), $bindings);

        try {
            $customers->createFor('host_1', new CustomerProfile(email: 'host@example.test'));
            $this->fail('Expected CustomerAlreadyBoundException');
        } catch (CustomerAlreadyBoundException $e) {
            $this->assertSame('host_1', $e->hostCustomerId);
            $this->assertSame('cus_existing', $e->existingVatlyCustomerId);
            $this->assertNull($e->attemptedVatlyCustomerId);
            $this->assertStringContainsString("create Vatly customer for host customer id 'host_1'", $e->getMessage());
            $this->assertStringContainsString("'cus_existing'", $e->getMessage());
        }
    }

    public function test_create_unattributed_records_without_binding_a_host(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_anon');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        $createCustomer->shouldReceive('execute')
            ->once()
            ->with(['email' => 'anon@example.test'])
            ->andReturn($apiCustomer);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('record')->with('cus_anon')->once();
        $bindings->shouldNotReceive('bind');

        $customers = new CustomerService($createCustomer, Mockery::mock(GetCustomer::class), $bindings);

        $result = $customers->createUnattributed(new CustomerProfile(email: 'anon@example.test'));

        $this->assertSame($apiCustomer, $result);
    }

    public function test_attribute_binds_when_host_is_unbound(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturnNull();
        $bindings->shouldReceive('bind')->with('cus_x', 'host_x')->once();

        $customers = new CustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $bindings,
        );

        $customers->attribute('cus_x', 'host_x');
    }

    public function test_attribute_is_idempotent_for_the_same_pair(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturn('cus_x');
        $bindings->shouldReceive('bind')->with('cus_x', 'host_x')->once();

        $customers = new CustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $bindings,
        );

        $customers->attribute('cus_x', 'host_x');
    }

    public function test_attribute_throws_when_host_is_bound_to_a_different_vatly_customer(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturn('cus_other');
        $bindings->shouldNotReceive('bind');

        $customers = new CustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $bindings,
        );

        try {
            $customers->attribute('cus_new', 'host_x');
            $this->fail('Expected CustomerAlreadyBoundException');
        } catch (CustomerAlreadyBoundException $e) {
            $this->assertSame('host_x', $e->hostCustomerId);
            $this->assertSame('cus_new', $e->attemptedVatlyCustomerId);
            $this->assertSame('cus_other', $e->existingVatlyCustomerId);
            $this->assertStringContainsString("attribute Vatly customer 'cus_new'", $e->getMessage());
            $this->assertStringContainsString("host_x", $e->getMessage());
            $this->assertStringContainsString("'cus_other'", $e->getMessage());
        }
    }

    public function test_find_by_host_customer_id_returns_customer_when_bound(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_bound');

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_1')->once()->andReturn('cus_bound');

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldReceive('execute')->with('cus_bound')->once()->andReturn($apiCustomer);

        $customers = new CustomerService(Mockery::mock(CreateCustomer::class), $getCustomer, $bindings);

        $this->assertSame($apiCustomer, $customers->findByHostCustomerId('host_1'));
    }

    public function test_find_by_host_customer_id_returns_null_when_unbound(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_unknown')->once()->andReturnNull();

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldNotReceive('execute');

        $customers = new CustomerService(Mockery::mock(CreateCustomer::class), $getCustomer, $bindings);

        $this->assertNull($customers->findByHostCustomerId('host_unknown'));
    }

    public function test_find_by_vatly_customer_id_proxies_to_the_action(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_zzz');

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldReceive('execute')->with('cus_zzz')->once()->andReturn($apiCustomer);

        $customers = new CustomerService(
            Mockery::mock(CreateCustomer::class),
            $getCustomer,
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertSame($apiCustomer, $customers->findByVatlyCustomerId('cus_zzz'));
    }

    public function test_host_customer_id_for_proxies_to_bindings(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_a')->once()->andReturn('host_a');

        $customers = new CustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $bindings,
        );

        $this->assertSame('host_a', $customers->hostCustomerIdFor('cus_a'));
    }

    private function makeApiCustomer(string $id): ApiCustomer
    {
        $client = Mockery::mock(VatlyApiClient::class);
        $customer = new ApiCustomer($client);
        $customer->id = $id;

        return $customer;
    }
}
