<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Data;

use DateTimeImmutable;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Tests\TestCase;

class UpdateSubscriptionDataTest extends TestCase
{
    public function test_cancellation_reason_defaults_to_null(): void
    {
        $data = new UpdateSubscriptionData();

        $this->assertNull($data->cancellationReason);
    }

    public function test_cancellation_reason_is_carried_verbatim(): void
    {
        $data = new UpdateSubscriptionData(cancellationReason: 'payment_failure');

        $this->assertSame('payment_failure', $data->cancellationReason);
    }

    public function test_cancellation_reason_sits_alongside_ends_at_and_status(): void
    {
        $endsAt = new DateTimeImmutable('2026-06-01T00:00:00Z');

        $data = new UpdateSubscriptionData(
            endsAt: $endsAt,
            status: 'canceled',
            cancellationReason: 'merchant_request',
        );

        $this->assertSame($endsAt, $data->endsAt);
        $this->assertSame('canceled', $data->status);
        $this->assertSame('merchant_request', $data->cancellationReason);
    }
}
