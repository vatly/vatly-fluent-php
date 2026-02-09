<?php

declare(strict_types=1);

use Vatly\Events\SubscriptionCanceledImmediately;
use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Events\SubscriptionStarted;
use Vatly\Events\UnsupportedWebhookReceived;
use Vatly\Events\WebhookReceived;
use Vatly\Webhooks\WebhookEventFactory;

beforeEach(function () {
    $this->factory = new WebhookEventFactory();
});

test('it parses webhook payload into WebhookReceived event', function () {
    $payload = [
        'eventName' => 'subscription.started',
        'resourceId' => 'sub_123',
        'resourceName' => 'subscription',
        'object' => ['data' => ['customerId' => 'cus_456']],
        'raisedAt' => '2024-01-15T10:00:00Z',
        'testmode' => true,
    ];

    $event = $this->factory->parsePayload($payload);

    expect($event)->toBeInstanceOf(WebhookReceived::class)
        ->and($event->eventName)->toBe('subscription.started')
        ->and($event->resourceId)->toBe('sub_123')
        ->and($event->resourceName)->toBe('subscription')
        ->and($event->testmode)->toBeTrue()
        ->and($event->getCustomerId())->toBe('cus_456');
});

test('it creates SubscriptionStarted event from webhook', function () {
    $webhook = new WebhookReceived(
        eventName: 'subscription.started',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: [
            'data' => [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = $this->factory->createFromWebhook($webhook);

    expect($event)->toBeInstanceOf(SubscriptionStarted::class)
        ->and($event->customerId)->toBe('cus_456')
        ->and($event->subscriptionId)->toBe('sub_123')
        ->and($event->planId)->toBe('plan_789')
        ->and($event->name)->toBe('Premium Plan')
        ->and($event->quantity)->toBe(1);
});

test('it creates SubscriptionCanceledImmediately event from webhook', function () {
    $webhook = new WebhookReceived(
        eventName: 'subscription.canceled_immediately',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: [
            'data' => [
                'customerId' => 'cus_456',
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = $this->factory->createFromWebhook($webhook);

    expect($event)->toBeInstanceOf(SubscriptionCanceledImmediately::class)
        ->and($event->customerId)->toBe('cus_456')
        ->and($event->subscriptionId)->toBe('sub_123');
});

test('it creates SubscriptionCanceledWithGracePeriod event from webhook', function () {
    $webhook = new WebhookReceived(
        eventName: 'subscription.canceled_with_grace_period',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: [
            'data' => [
                'customerId' => 'cus_456',
                'endsAt' => '2024-02-15T10:00:00Z',
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = $this->factory->createFromWebhook($webhook);

    expect($event)->toBeInstanceOf(SubscriptionCanceledWithGracePeriod::class)
        ->and($event->customerId)->toBe('cus_456')
        ->and($event->subscriptionId)->toBe('sub_123')
        ->and($event->endsAt)->toBeInstanceOf(DateTimeInterface::class);
});

test('it creates UnsupportedWebhookReceived for unknown events', function () {
    $webhook = new WebhookReceived(
        eventName: 'unknown.event',
        resourceId: 'res_123',
        resourceName: 'unknown',
        object: [],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = $this->factory->createFromWebhook($webhook);

    expect($event)->toBeInstanceOf(UnsupportedWebhookReceived::class)
        ->and($event->eventName)->toBe('unknown.event');
});

test('it returns list of supported events', function () {
    $supported = $this->factory->getSupportedEvents();

    expect($supported)->toContain('subscription.started')
        ->and($supported)->toContain('subscription.canceled_immediately')
        ->and($supported)->toContain('subscription.canceled_with_grace_period');
});

test('it checks if event is supported', function () {
    expect($this->factory->isSupported('subscription.started'))->toBeTrue()
        ->and($this->factory->isSupported('unknown.event'))->toBeFalse();
});
