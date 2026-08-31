<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Data;

use Vatly\Fluent\Data\UpdateCustomerData;
use Vatly\Fluent\Tests\TestCase;

class UpdateCustomerDataTest extends TestCase
{
    public function test_to_payload_includes_both_fields_when_set(): void
    {
        $data = new UpdateCustomerData(name: 'Jane Doe', email: 'jane@example.test');

        $this->assertSame(
            ['name' => 'Jane Doe', 'email' => 'jane@example.test'],
            $data->toPayload(),
        );
    }

    public function test_to_payload_strips_unset_fields(): void
    {
        $this->assertSame(['name' => 'Only Name'], (new UpdateCustomerData(name: 'Only Name'))->toPayload());
        $this->assertSame(['email' => 'only@example.test'], (new UpdateCustomerData(email: 'only@example.test'))->toPayload());
    }

    public function test_to_payload_is_empty_when_nothing_is_set(): void
    {
        $this->assertSame([], (new UpdateCustomerData())->toPayload());
    }
}
