<?php

declare(strict_types=1);

use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\WebhookReceived;

test('it has correct vatly event name constant', function () {
    expect(OrderPaid::VATLY_EVENT_NAME)->toBe('order.paid');
});

test('it can be instantiated with all properties', function () {
    $event = new OrderPaid(
        customerId: 'cus_123',
        orderId: 'ord_456',
        total: 9900,
        currency: 'EUR',
        invoiceNumber: 'INV-2024-001',
        paymentMethod: 'credit_card',
    );

    expect($event->customerId)->toBe('cus_123')
        ->and($event->orderId)->toBe('ord_456')
        ->and($event->total)->toBe(9900)
        ->and($event->currency)->toBe('EUR')
        ->and($event->invoiceNumber)->toBe('INV-2024-001')
        ->and($event->paymentMethod)->toBe('credit_card');
});

test('it creates from webhook', function () {
    $webhook = new WebhookReceived(
        eventName: 'order.paid',
        resourceId: 'ord_123',
        resourceName: 'order',
        object: [
            'data' => [
                'customerId' => 'cus_456',
                'total' => 4999,
                'currency' => 'USD',
                'invoiceNumber' => 'INV-2024-002',
                'paymentMethod' => 'ideal',
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = OrderPaid::fromWebhook($webhook);

    expect($event->customerId)->toBe('cus_456')
        ->and($event->orderId)->toBe('ord_123')
        ->and($event->total)->toBe(4999)
        ->and($event->currency)->toBe('USD')
        ->and($event->invoiceNumber)->toBe('INV-2024-002')
        ->and($event->paymentMethod)->toBe('ideal');
});

test('it creates from webhook with nullable fields', function () {
    $webhook = new WebhookReceived(
        eventName: 'order.paid',
        resourceId: 'ord_789',
        resourceName: 'order',
        object: [
            'data' => [
                'customerId' => 'cus_012',
                'total' => 1500,
                'currency' => 'GBP',
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = OrderPaid::fromWebhook($webhook);

    expect($event->customerId)->toBe('cus_012')
        ->and($event->orderId)->toBe('ord_789')
        ->and($event->total)->toBe(1500)
        ->and($event->currency)->toBe('GBP')
        ->and($event->invoiceNumber)->toBeNull()
        ->and($event->paymentMethod)->toBeNull();
});
