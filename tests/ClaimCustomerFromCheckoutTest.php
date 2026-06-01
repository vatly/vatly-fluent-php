<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\GetCheckout;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

class ClaimCustomerFromCheckoutTest extends TestCase
{
    private const CHECKOUT_ID = 'checkout_abc';
    private const VATLY_CUSTOMER_ID = 'customer_vatly';
    private const HOST_CUSTOMER_ID = '42';

    public function test_attributes_customer_and_returns_true_for_an_unbound_host(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with(self::HOST_CUSTOMER_ID)->andReturn(null);
        $bindings->shouldReceive('bind')->once()->with(self::VATLY_CUSTOMER_ID, self::HOST_CUSTOMER_ID);

        $vatly = $this->vatlyWithBindings($bindings);
        $this->fakeCheckout($vatly, self::VATLY_CUSTOMER_ID);

        $this->assertTrue(
            $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID)
        );
    }

    public function test_is_idempotent_for_the_same_host_customer_pair(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with(self::HOST_CUSTOMER_ID)->andReturn(self::VATLY_CUSTOMER_ID);
        $bindings->shouldReceive('bind')->with(self::VATLY_CUSTOMER_ID, self::HOST_CUSTOMER_ID);

        $vatly = $this->vatlyWithBindings($bindings);
        $this->fakeCheckout($vatly, self::VATLY_CUSTOMER_ID);

        $this->assertTrue(
            $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID)
        );
    }

    public function test_throws_on_a_cross_host_conflict(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldReceive('vatlyCustomerIdFor')->with(self::HOST_CUSTOMER_ID)->andReturn('customer_someone_else');
        $bindings->shouldNotReceive('bind');

        $vatly = $this->vatlyWithBindings($bindings);
        $this->fakeCheckout($vatly, self::VATLY_CUSTOMER_ID);

        $this->expectException(CustomerAlreadyBoundException::class);

        $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID);
    }

    public function test_returns_false_for_an_unknown_checkout(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('bind');

        $vatly = $this->vatlyWithBindings($bindings);

        $getCheckout = Mockery::mock(GetCheckout::class);
        $getCheckout->shouldReceive('execute')->with(self::CHECKOUT_ID)
            ->andThrow(new ApiException('Error 404 executing API call', 404));
        $this->setPrivate($vatly, 'getCheckout', $getCheckout);

        $this->assertFalse(
            $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID)
        );
    }

    public function test_rethrows_non_not_found_api_errors(): void
    {
        $vatly = $this->vatlyWithBindings(Mockery::mock(CustomerBindingRepository::class));

        $getCheckout = Mockery::mock(GetCheckout::class);
        $getCheckout->shouldReceive('execute')->with(self::CHECKOUT_ID)
            ->andThrow(new ApiException('Error 500 executing API call', 500));
        $this->setPrivate($vatly, 'getCheckout', $getCheckout);

        $this->expectException(ApiException::class);

        $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID);
    }

    public function test_returns_false_when_the_checkout_has_no_customer_yet(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('bind');

        $vatly = $this->vatlyWithBindings($bindings);
        $this->fakeCheckout($vatly, null);

        $this->assertFalse(
            $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID)
        );
    }

    public function test_returns_false_for_an_empty_customer_id(): void
    {
        $bindings = Mockery::mock(CustomerBindingRepository::class);
        $bindings->shouldNotReceive('bind');

        $vatly = $this->vatlyWithBindings($bindings);
        $this->fakeCheckout($vatly, '');

        $this->assertFalse(
            $vatly->claimCustomerFromCheckout(self::CHECKOUT_ID, self::HOST_CUSTOMER_ID)
        );
    }

    public function test_customer_id_from_checkout_returns_the_attached_customer_id(): void
    {
        $vatly = $this->vatlyWithBindings(Mockery::mock(CustomerBindingRepository::class));
        $this->fakeCheckout($vatly, self::VATLY_CUSTOMER_ID);

        $this->assertSame(
            self::VATLY_CUSTOMER_ID,
            $vatly->customerIdFromCheckout(self::CHECKOUT_ID)
        );
    }

    public function test_customer_id_from_checkout_returns_null_for_an_unknown_checkout(): void
    {
        $vatly = $this->vatlyWithBindings(Mockery::mock(CustomerBindingRepository::class));

        $getCheckout = Mockery::mock(GetCheckout::class);
        $getCheckout->shouldReceive('execute')->with(self::CHECKOUT_ID)
            ->andThrow(new ApiException('Error 404 executing API call', 404));
        $this->setPrivate($vatly, 'getCheckout', $getCheckout);

        $this->assertNull($vatly->customerIdFromCheckout(self::CHECKOUT_ID));
    }

    public function test_customer_id_from_checkout_returns_null_when_no_customer_yet(): void
    {
        $vatly = $this->vatlyWithBindings(Mockery::mock(CustomerBindingRepository::class));
        $this->fakeCheckout($vatly, null);

        $this->assertNull($vatly->customerIdFromCheckout(self::CHECKOUT_ID));
    }

    private function vatlyWithBindings(CustomerBindingRepository $bindings): Vatly
    {
        return new Vatly(new Wiring(
            config: new ArrayConfiguration(['api_key' => 'test_abcdefghijklmnopqrstuvwxyz']),
            customerBindings: $bindings,
        ));
    }

    /**
     * Stub the GetCheckout action so it returns a Checkout carrying the given
     * customer id, without hitting the API.
     */
    private function fakeCheckout(Vatly $vatly, ?string $customerId): void
    {
        $checkout = new Checkout($vatly->getApiClient());
        $checkout->customerId = $customerId;

        $getCheckout = Mockery::mock(GetCheckout::class);
        $getCheckout->shouldReceive('execute')->with(self::CHECKOUT_ID)->andReturn($checkout);

        $this->setPrivate($vatly, 'getCheckout', $getCheckout);
    }

    private function setPrivate(object $target, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($target, $property);
        $ref->setValue($target, $value);
    }
}
