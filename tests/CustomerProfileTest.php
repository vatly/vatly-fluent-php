<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Vatly\Fluent\CustomerProfile;

class CustomerProfileTest extends TestCase
{
    public function test_it_holds_the_given_values(): void
    {
        $profile = new CustomerProfile(vatlyId: 'cus_abc', email: 'a@b.test', name: 'Acme');

        $this->assertSame('cus_abc', $profile->vatlyId);
        $this->assertSame('a@b.test', $profile->email);
        $this->assertSame('Acme', $profile->name);
    }

    public function test_to_payload_strips_nulls(): void
    {
        $profile = new CustomerProfile(email: 'a@b.test');

        $this->assertSame(['email' => 'a@b.test'], $profile->toPayload());
    }

    public function test_to_payload_omits_vatly_id_since_it_belongs_in_the_url(): void
    {
        $profile = new CustomerProfile(vatlyId: 'cus_abc', email: 'a@b.test');

        $payload = $profile->toPayload();

        $this->assertArrayNotHasKey('vatlyId', $payload);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertSame('a@b.test', $payload['email']);
    }

    public function test_to_payload_is_empty_when_only_vatly_id_is_set(): void
    {
        $profile = new CustomerProfile(vatlyId: 'cus_abc');

        $this->assertSame([], $profile->toPayload());
    }

    public function test_defaults_all_fields_to_null(): void
    {
        $profile = new CustomerProfile();

        $this->assertNull($profile->vatlyId);
        $this->assertNull($profile->email);
        $this->assertNull($profile->name);
        $this->assertSame([], $profile->toPayload());
    }
}
