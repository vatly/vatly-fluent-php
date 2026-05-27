<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;

/**
 * Composition-time dependencies passed to {@see Vatly}.
 *
 * Only `config` is required. Optional impls (repos, dispatcher) can be
 * omitted for api-only mode; calling a method on `Vatly` that needs an
 * absent dependency raises {@see \Vatly\Fluent\Exceptions\IncompleteWiring}.
 *
 * Drivers (Laravel, etc.) construct one of these from their container and
 * pass it to `new Vatly($wiring)` — typically bound as a singleton.
 */
final readonly class Wiring
{
    public function __construct(
        public ConfigurationInterface $config,
        public ?SubscriptionRepositoryInterface $subscriptions = null,
        public ?CustomerRepositoryInterface $customers = null,
        public ?OrderRepositoryInterface $orders = null,
        public ?WebhookCallRepositoryInterface $webhookCalls = null,
        public ?EventDispatcherInterface $events = null,
    ) {
        //
    }
}
