<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnStarted;

class WebhookProcessorFactory
{
    /**
     * Build a WebhookProcessor wired with the standard reactions.
     *
     * Drivers call this from their bootstrap to avoid re-deriving the
     * reaction registration list on each install.
     *
     * @param WebhookReactionInterface[] $additionalReactions
     */
    public static function create(
        ConfigurationInterface $config,
        SubscriptionRepositoryInterface $subscriptions,
        OrderRepositoryInterface $orders,
        WebhookCallRepositoryInterface $webhookCalls,
        EventDispatcherInterface $dispatcher,
        GetOrder $getOrder,
        array $additionalReactions = [],
    ): WebhookProcessor {
        return new WebhookProcessor(
            signatureVerifier: new SignatureVerifier(),
            eventFactory: new WebhookEventFactory($getOrder),
            repository: $webhookCalls,
            dispatcher: $dispatcher,
            webhookSecret: $config->getWebhookSecret() ?? '',
            reactions: [
                new SyncSubscriptionOnStarted($subscriptions, $dispatcher),
                new CancelSubscriptionOnCanceled($subscriptions),
                new StoreOrderOnPaid($orders),
                ...$additionalReactions,
            ],
        );
    }
}
