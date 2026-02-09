<?php

declare(strict_types=1);

use Vatly\Events\SubscriptionCanceledImmediately;
use Vatly\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Events\WebhookReceived;

describe('SubscriptionCanceledImmediately', function () {
    test('it has correct vatly event name constant', function () {
        expect(SubscriptionCanceledImmediately::VATLY_EVENT_NAME)->toBe('subscription.canceled_immediately');
    });

    test('it can be instantiated with properties', function () {
        $event = new SubscriptionCanceledImmediately(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
        );

        expect($event->customerId)->toBe('cus_123')
            ->and($event->subscriptionId)->toBe('sub_456');
    });

    test('it creates from webhook', function () {
        $webhook = new WebhookReceived(
            eventName: 'subscription.canceled_immediately',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: ['data' => ['customerId' => 'cus_456']],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = SubscriptionCanceledImmediately::fromWebhook($webhook);

        expect($event->customerId)->toBe('cus_456')
            ->and($event->subscriptionId)->toBe('sub_123');
    });
});

describe('SubscriptionCanceledWithGracePeriod', function () {
    test('it has correct vatly event name constant', function () {
        expect(SubscriptionCanceledWithGracePeriod::VATLY_EVENT_NAME)->toBe('subscription.canceled_with_grace_period');
    });

    test('it can be instantiated with properties', function () {
        $endsAt = new DateTimeImmutable('2024-02-15T10:00:00Z');

        $event = new SubscriptionCanceledWithGracePeriod(
            customerId: 'cus_123',
            subscriptionId: 'sub_456',
            endsAt: $endsAt,
        );

        expect($event->customerId)->toBe('cus_123')
            ->and($event->subscriptionId)->toBe('sub_456')
            ->and($event->endsAt)->toBe($endsAt);
    });

    test('it creates from webhook with parsed date', function () {
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

        $event = SubscriptionCanceledWithGracePeriod::fromWebhook($webhook);

        expect($event->customerId)->toBe('cus_456')
            ->and($event->subscriptionId)->toBe('sub_123')
            ->and($event->endsAt)->toBeInstanceOf(DateTimeInterface::class)
            ->and($event->endsAt->format('Y-m-d'))->toBe('2024-02-15');
    });
});
