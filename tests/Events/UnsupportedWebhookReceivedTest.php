<?php

declare(strict_types=1);

use Vatly\Events\UnsupportedWebhookReceived;
use Vatly\Events\WebhookReceived;

test('it can be instantiated with all properties', function () {
    $event = new UnsupportedWebhookReceived(
        eventName: 'unknown.event',
        resourceId: 'res_123',
        resourceName: 'resource',
        object: ['data' => ['key' => 'value']],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: true,
    );

    expect($event->eventName)->toBe('unknown.event')
        ->and($event->resourceId)->toBe('res_123')
        ->and($event->resourceName)->toBe('resource')
        ->and($event->object)->toBe(['data' => ['key' => 'value']])
        ->and($event->raisedAt)->toBe('2024-01-15T10:00:00Z')
        ->and($event->testmode)->toBeTrue();
});

test('it creates from WebhookReceived', function () {
    $webhook = new WebhookReceived(
        eventName: 'unknown.event.type',
        resourceId: 'xyz_789',
        resourceName: 'unknown_resource',
        object: ['foo' => 'bar'],
        raisedAt: '2024-06-01T12:00:00Z',
        testmode: false,
    );

    $event = UnsupportedWebhookReceived::fromWebhook($webhook);

    expect($event)->toBeInstanceOf(UnsupportedWebhookReceived::class)
        ->and($event->eventName)->toBe('unknown.event.type')
        ->and($event->resourceId)->toBe('xyz_789')
        ->and($event->resourceName)->toBe('unknown_resource')
        ->and($event->object)->toBe(['foo' => 'bar'])
        ->and($event->raisedAt)->toBe('2024-06-01T12:00:00Z')
        ->and($event->testmode)->toBeFalse();
});
