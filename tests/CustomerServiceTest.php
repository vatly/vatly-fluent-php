<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Resources\Customer as ApiCustomer;
use Vatly\API\Resources\CustomerCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\ListCustomersByEmail;
use Vatly\Fluent\Actions\UpdateCustomer;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\CustomerService;
use Vatly\Fluent\Data\UpdateCustomerData;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;

class CustomerServiceTest extends TestCase
{
    /**
     * Build a {@see CustomerService} for the tests. The email-recovery action is
     * only exercised by the dedicated find-by-email tests, so it defaults to a
     * bare mock everywhere else.
     */
    private function makeCustomerService(
        CreateCustomer $createCustomer,
        GetCustomer $getCustomer,
        UpdateCustomer $updateCustomer,
        CustomerBindingRepository $bindings,
        ?ListCustomersByEmail $listCustomersByEmail = null,
    ): CustomerService {
        return new CustomerService(
            $createCustomer,
            $getCustomer,
            $updateCustomer,
            $bindings,
            $listCustomersByEmail ?? Mockery::mock(ListCustomersByEmail::class),
        );
    }

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

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);
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

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);

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

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);

        $result = $customers->createUnattributed(new CustomerProfile(email: 'anon@example.test'));

        $this->assertSame($apiCustomer, $result);
    }

    public function test_create_for_forwards_additional_payload_keys_to_the_api(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_new');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        $createCustomer->shouldReceive('execute')
            ->once()
            ->with([
                'email' => 'host@example.test',
                'name' => 'Host Name',
                'locale' => 'nl_NL',
                'metadata' => ['internal_id' => 42],
            ])
            ->andReturn($apiCustomer);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_1')->once()->andReturnNull();
        $bindings->shouldReceive('bind')->with('cus_new', 'host_1')->once();

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);
        $profile = new CustomerProfile(email: 'host@example.test', name: 'Host Name');

        $customers->createFor('host_1', $profile, [
            'locale' => 'nl_NL',
            'metadata' => ['internal_id' => 42],
        ]);
    }

    public function test_create_for_additional_payload_overrides_profile_defaults(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_new');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        // additionalPayload['email'] wins over profile.email
        $createCustomer->shouldReceive('execute')
            ->once()
            ->with(['email' => 'override@example.test', 'name' => 'Host Name'])
            ->andReturn($apiCustomer);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->andReturnNull();
        $bindings->shouldReceive('bind');

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);
        $profile = new CustomerProfile(email: 'host@example.test', name: 'Host Name');

        $customers->createFor('host_1', $profile, ['email' => 'override@example.test']);
    }

    public function test_create_unattributed_forwards_additional_payload_keys_to_the_api(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_anon');

        $createCustomer = Mockery::mock(CreateCustomer::class);
        $createCustomer->shouldReceive('execute')
            ->once()
            ->with([
                'email' => 'anon@example.test',
                'locale' => 'de_DE',
            ])
            ->andReturn($apiCustomer);

        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('record')->with('cus_anon')->once();

        $customers = $this->makeCustomerService($createCustomer, Mockery::mock(GetCustomer::class), Mockery::mock(UpdateCustomer::class), $bindings);

        $customers->createUnattributed(
            new CustomerProfile(email: 'anon@example.test'),
            ['locale' => 'de_DE'],
        );
    }

    public function test_attribute_binds_when_host_is_unbound(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturnNull();
        $bindings->shouldReceive('bind')->with('cus_x', 'host_x')->once();

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            $bindings,
        );

        $customers->attribute('cus_x', 'host_x');
    }

    public function test_attribute_is_idempotent_for_the_same_pair(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturn('cus_x');
        $bindings->shouldReceive('bind')->with('cus_x', 'host_x')->once();

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            $bindings,
        );

        $customers->attribute('cus_x', 'host_x');
    }

    public function test_attribute_throws_when_host_is_bound_to_a_different_vatly_customer(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_x')->once()->andReturn('cus_other');
        $bindings->shouldNotReceive('bind');

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
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

        $customers = $this->makeCustomerService(Mockery::mock(CreateCustomer::class), $getCustomer, Mockery::mock(UpdateCustomer::class), $bindings);

        $this->assertSame($apiCustomer, $customers->findByHostCustomerId('host_1'));
    }

    public function test_find_by_host_customer_id_returns_null_when_unbound(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with('host_unknown')->once()->andReturnNull();

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldNotReceive('execute');

        $customers = $this->makeCustomerService(Mockery::mock(CreateCustomer::class), $getCustomer, Mockery::mock(UpdateCustomer::class), $bindings);

        $this->assertNull($customers->findByHostCustomerId('host_unknown'));
    }

    public function test_find_by_vatly_customer_id_proxies_to_the_action(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_zzz');

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldReceive('execute')->with('cus_zzz')->once()->andReturn($apiCustomer);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            $getCustomer,
            Mockery::mock(UpdateCustomer::class),
            Mockery::mock(CustomerBindingRepository::class),
        );

        $this->assertSame($apiCustomer, $customers->findByVatlyCustomerId('cus_zzz'));
    }

    public function test_update_proxies_identity_fields_to_the_action(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_upd');

        $updateCustomer = Mockery::mock(UpdateCustomer::class);
        $updateCustomer->shouldReceive('execute')
            ->once()
            ->with('cus_upd', ['name' => 'Jane Doe', 'email' => 'jane@example.test'])
            ->andReturn($apiCustomer);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $updateCustomer,
            Mockery::mock(CustomerBindingRepository::class),
        );

        $result = $customers->update('cus_upd', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
        ]);

        $this->assertSame($apiCustomer, $result);
    }

    public function test_update_does_not_touch_the_binding_repository(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_upd');

        $updateCustomer = Mockery::mock(UpdateCustomer::class);
        $updateCustomer->shouldReceive('execute')->once()->andReturn($apiCustomer);

        // A pure identity update must not re-bind or record anything.
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('bind');
        $bindings->shouldNotReceive('record');

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $updateCustomer,
            $bindings,
        );

        $customers->update('cus_upd', ['name' => null]);
    }

    public function test_host_customer_id_for_proxies_to_bindings(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('hostCustomerIdFor')->with('cus_a')->once()->andReturn('host_a');

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            $bindings,
        );

        $this->assertSame('host_a', $customers->hostCustomerIdFor('cus_a'));
    }

    public function test_update_accepts_an_update_customer_data_dto(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_upd');

        $updateCustomer = Mockery::mock(UpdateCustomer::class);
        $updateCustomer->shouldReceive('execute')
            ->once()
            ->with('cus_upd', ['name' => 'Jane Doe', 'email' => 'jane@example.test'])
            ->andReturn($apiCustomer);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $updateCustomer,
            Mockery::mock(CustomerBindingRepository::class),
        );

        $result = $customers->update('cus_upd', new UpdateCustomerData(
            name: 'Jane Doe',
            email: 'jane@example.test',
        ));

        $this->assertSame($apiCustomer, $result);
    }

    public function test_update_dto_sends_only_the_fields_that_are_set(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_upd');

        $updateCustomer = Mockery::mock(UpdateCustomer::class);
        // email is left null on the DTO, so it must not be sent.
        $updateCustomer->shouldReceive('execute')
            ->once()
            ->with('cus_upd', ['name' => 'Only Name'])
            ->andReturn($apiCustomer);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            $updateCustomer,
            Mockery::mock(CustomerBindingRepository::class),
        );

        $customers->update('cus_upd', new UpdateCustomerData(name: 'Only Name'));
    }

    public function test_identity_reads_back_name_and_email(): void
    {
        $apiCustomer = $this->makeApiCustomer('cus_read');
        $apiCustomer->name = 'Jane Doe';
        $apiCustomer->email = 'jane@example.test';

        $bindings = Mockery::mock(CustomerBindingRepository::class);

        $getCustomer = Mockery::mock(GetCustomer::class);
        $getCustomer->shouldReceive('execute')->with('cus_read')->once()->andReturn($apiCustomer);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            $getCustomer,
            Mockery::mock(UpdateCustomer::class),
            $bindings,
        );

        $this->assertSame(
            ['name' => 'Jane Doe', 'email' => 'jane@example.test'],
            $customers->identity('cus_read'),
        );
    }

    public function test_find_by_email_returns_the_collection_from_the_action(): void
    {
        $collection = $this->makeCustomerCollection([
            $this->makeApiCustomer('cus_1'),
            $this->makeApiCustomer('cus_2'),
        ]);

        $listByEmail = Mockery::mock(ListCustomersByEmail::class);
        $listByEmail->shouldReceive('execute')
            ->once()
            ->with('jane@example.test')
            ->andReturn($collection);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            Mockery::mock(CustomerBindingRepository::class),
            $listByEmail,
        );

        $this->assertSame($collection, $customers->findByEmail('jane@example.test'));
    }

    public function test_find_one_by_email_returns_the_first_match(): void
    {
        $first = $this->makeApiCustomer('cus_1');
        $collection = $this->makeCustomerCollection([$first, $this->makeApiCustomer('cus_2')]);

        $listByEmail = Mockery::mock(ListCustomersByEmail::class);
        $listByEmail->shouldReceive('execute')->with('jane@example.test')->andReturn($collection);

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            Mockery::mock(CustomerBindingRepository::class),
            $listByEmail,
        );

        $this->assertSame($first, $customers->findOneByEmail('jane@example.test'));
    }

    public function test_find_one_by_email_returns_null_when_no_customer_matches(): void
    {
        $listByEmail = Mockery::mock(ListCustomersByEmail::class);
        $listByEmail->shouldReceive('execute')->with('nobody@example.test')->andReturn($this->makeCustomerCollection([]));

        $customers = $this->makeCustomerService(
            Mockery::mock(CreateCustomer::class),
            Mockery::mock(GetCustomer::class),
            Mockery::mock(UpdateCustomer::class),
            Mockery::mock(CustomerBindingRepository::class),
            $listByEmail,
        );

        $this->assertNull($customers->findOneByEmail('nobody@example.test'));
    }

    private function makeApiCustomer(string $id): ApiCustomer
    {
        $client = Mockery::mock(VatlyApiClient::class);
        $customer = new ApiCustomer($client);
        $customer->id = $id;

        return $customer;
    }

    /**
     * @param array<int, ApiCustomer> $customers
     */
    private function makeCustomerCollection(array $customers): CustomerCollection
    {
        $collection = new CustomerCollection(Mockery::mock(VatlyApiClient::class), count($customers), null);

        foreach ($customers as $customer) {
            $collection[] = $customer;
        }

        return $collection;
    }
}
