<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Vatly\Fluent\Events\CheckoutCanceled;
use Vatly\Fluent\Events\CheckoutExpired;
use Vatly\Fluent\Events\CheckoutFailed;
use Vatly\Fluent\Events\CheckoutPaid;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class CheckoutEventsTest extends TestCase
{
    public function test_paid_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('checkout.paid', CheckoutPaid::VATLY_EVENT_NAME);
    }

    public function test_failed_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('checkout.failed', CheckoutFailed::VATLY_EVENT_NAME);
    }

    public function test_canceled_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('checkout.canceled', CheckoutCanceled::VATLY_EVENT_NAME);
    }

    public function test_expired_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('checkout.expired', CheckoutExpired::VATLY_EVENT_NAME);
    }

    public function test_paid_creates_from_webhook_with_order_and_customer_links(): void
    {
        $event = CheckoutPaid::fromWebhook($this->webhook('checkout.paid', [
            'customerId' => 'cus_456',
            'orderId' => 'ord_789',
            'status' => 'paid',
            'metadata' => ['cart_id' => 'cart_1'],
        ]));

        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_789', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(['cart_id' => 'cart_1'], $event->metadata);
    }

    public function test_paid_defaults_status_and_nulls_absent_fields(): void
    {
        // An anonymous checkout that just paid may not echo every field back;
        // the type stays honest (null) rather than synthesizing empty strings,
        // and status falls back to the lifecycle this event represents.
        $event = CheckoutPaid::fromWebhook($this->webhook('checkout.paid', []));

        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertNull($event->customerId);
        $this->assertNull($event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertNull($event->metadata);
    }

    public function test_failed_creates_from_webhook_with_null_order(): void
    {
        $event = CheckoutFailed::fromWebhook($this->webhook('checkout.failed', [
            'customerId' => 'cus_456',
            'status' => 'failed',
        ]));

        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertNull($event->orderId);
        $this->assertSame('failed', $event->status);
    }

    public function test_canceled_creates_from_webhook(): void
    {
        $event = CheckoutCanceled::fromWebhook($this->webhook('checkout.canceled', [
            'customerId' => 'cus_456',
        ]));

        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('canceled', $event->status);
    }

    public function test_expired_creates_from_webhook(): void
    {
        $event = CheckoutExpired::fromWebhook($this->webhook('checkout.expired', []));

        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertNull($event->customerId);
        $this->assertSame('expired', $event->status);
    }

    public function test_metadata_normalizes_stdclass_into_array(): void
    {
        // The factory deep-converts the payload to arrays, but guard the
        // object path so an event built from a raw stdClass payload stays
        // on the same array shape consumers expect.
        $event = CheckoutPaid::fromWebhook($this->webhook('checkout.paid', [
            'metadata' => (object) ['order_id' => '123456'],
        ]));

        $this->assertSame(['order_id' => '123456'], $event->metadata);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function webhook(string $eventName, array $object): WebhookReceived
    {
        return new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: $eventName,
            entityType: 'checkout',
            entityId: 'checkout_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: $object,
        );
    }
}
