<?php

declare(strict_types=1);

use Vatly\Events\SubscriptionStarted;
use Vatly\Events\WebhookReceived;

test('it has correct vatly event name constant', function () {
    expect(SubscriptionStarted::VATLY_EVENT_NAME)->toBe('subscription.started');
});

test('it has default type constant', function () {
    expect(SubscriptionStarted::DEFAULT_TYPE)->toBe('default');
});

test('it can be instantiated with all properties', function () {
    $event = new SubscriptionStarted(
        customerId: 'cus_123',
        subscriptionId: 'sub_456',
        planId: 'plan_789',
        type: 'premium',
        name: 'Premium Plan',
        quantity: 2,
    );

    expect($event->customerId)->toBe('cus_123')
        ->and($event->subscriptionId)->toBe('sub_456')
        ->and($event->planId)->toBe('plan_789')
        ->and($event->type)->toBe('premium')
        ->and($event->name)->toBe('Premium Plan')
        ->and($event->quantity)->toBe(2);
});

test('it creates from webhook', function () {
    $webhook = new WebhookReceived(
        eventName: 'subscription.started',
        resourceId: 'sub_123',
        resourceName: 'subscription',
        object: [
            'data' => [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Basic Plan',
                'quantity' => 1,
            ],
        ],
        raisedAt: '2024-01-15T10:00:00Z',
        testmode: false,
    );

    $event = SubscriptionStarted::fromWebhook($webhook);

    expect($event->customerId)->toBe('cus_456')
        ->and($event->subscriptionId)->toBe('sub_123')
        ->and($event->planId)->toBe('plan_789')
        ->and($event->type)->toBe('default')
        ->and($event->name)->toBe('Basic Plan')
        ->and($event->quantity)->toBe(1);
});
