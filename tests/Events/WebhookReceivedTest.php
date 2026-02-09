<?php

declare(strict_types=1);

use Vatly\Events\WebhookReceived;

test('it can be instantiated with all properties', function () {
    $event = new WebhookReceived(
        eventName: 'subscription.started',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: ['data' => ['customerId' => 'cus_456']],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: true,
    );

    expect($event->eventName)->toBe('subscription.started')
        ->and($event->resourceId)->toBe('sub_123')
        ->and($event->resourceName)->toBe('subscription')
        ->and($event->object)->toBe(['data' => ['customerId' => 'cus_456']])
        ->and($event->raisedAt)->toBe('2024-01-15T10:00:00Z')
        ->and($event->testmode)->toBeTrue();
});

test('it converts to array', function () {
    $event = new WebhookReceived(
        eventName: 'subscription.started',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: ['data' => []],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $array = $event->toArray();

    expect($array)->toHaveKeys(['eventName', 'resourceId', 'resourceName', 'object', 'raisedAt', 'testmode'])
        ->and($array['eventName'])->toBe('subscription.started');
});

test('it extracts customer ID from object', function () {
    $event = new WebhookReceived(
        eventName: 'subscription.started',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: ['data' => ['customerId' => 'cus_456']],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    expect($event->getCustomerId())->toBe('cus_456');
});

test('it returns null when customer ID not present', function () {
    $event = new WebhookReceived(
        eventName: 'test.event',
        resourceId: 'res_123',
        resourceName: 'resource',
        object: ['data' => []],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    expect($event->getCustomerId())->toBeNull();
});
