<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\RefundRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;

/**
 * Composition-time dependencies passed to {@see Vatly}.
 *
 * Only `config` is required. Optional impls (repos, dispatcher) can be
 * omitted for api-only mode; calling a method on `Vatly` that needs an
 * absent dependency raises {@see \Vatly\Fluent\Exceptions\IncompleteWiringException}.
 *
 * Drivers (Laravel, WordPress, etc.) construct one of these from their
 * container and pass it to `new Vatly($wiring)` — typically bound as
 * a singleton. Drivers that ship plugin-specific webhook reactions
 * (e.g. PMPro membership assignment, FluentCart order confirmation)
 * supply them via `additionalWebhookReactions`.
 */
final class Wiring
{
    /**
     * @param WebhookReactionInterface[] $additionalWebhookReactions
     */
    public function __construct(
        public readonly ConfigurationInterface $config,
        public readonly ?SubscriptionRepositoryInterface $subscriptions = null,
        public readonly ?OrderRepositoryInterface $orders = null,
        public readonly ?RefundRepositoryInterface $refunds = null,
        public readonly ?ChargebackRepositoryInterface $chargebacks = null,
        public readonly ?WebhookCallRepositoryInterface $webhookCalls = null,
        public readonly ?EventDispatcherInterface $events = null,
        public readonly ?CustomerBindingRepository $customerBindings = null,
        public readonly array $additionalWebhookReactions = [],
    ) {
        //
    }
}
