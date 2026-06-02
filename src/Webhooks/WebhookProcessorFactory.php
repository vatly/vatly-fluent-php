<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetRefund;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\RefundRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Webhooks\Reactions\CancelOrderOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\EndSubscriptionOnGracePeriodCompleted;
use Vatly\Fluent\Webhooks\Reactions\ResumeSubscriptionOnResumed;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaymentFailed;
use Vatly\Fluent\Webhooks\Reactions\SyncRefundOnStatusChange;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnBillingUpdated;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnStarted;

class WebhookProcessorFactory
{
    /**
     * Build a WebhookProcessor wired with the standard reactions.
     *
     * Drivers call this from their bootstrap to avoid re-deriving the
     * reaction registration list on each install.
     *
     * `getRefund` and `refunds` are both optional and back-compatible: a driver
     * that only wires subscriptions/orders can keep calling this exactly as
     * before. Pass `getRefund` (and `refunds`) only to opt into refund handling
     * — when `getRefund` is null, `refund.*` webhooks degrade to
     * {@see \Vatly\Fluent\Events\UnsupportedWebhookReceived} (the pre-refund
     * behavior), and the {@see SyncRefundOnStatusChange} reaction is registered
     * only when `refunds` is supplied.
     *
     * @param WebhookReactionInterface[] $additionalReactions
     */
    public static function create(
        ConfigurationInterface $config,
        SubscriptionRepositoryInterface $subscriptions,
        OrderRepositoryInterface $orders,
        WebhookCallRepositoryInterface $webhookCalls,
        EventDispatcherInterface $dispatcher,
        CustomerBindingRepository $bindings,
        GetOrder $getOrder,
        GetSubscription $getSubscription,
        ?GetRefund $getRefund = null,
        ?RefundRepositoryInterface $refunds = null,
        array $additionalReactions = [],
    ): WebhookProcessor {
        $reactions = [
            new SyncSubscriptionOnStarted($subscriptions, $bindings, $dispatcher),
            new SyncSubscriptionOnBillingUpdated($subscriptions),
            new ResumeSubscriptionOnResumed($subscriptions),
            new CancelSubscriptionOnCanceled($subscriptions),
            new EndSubscriptionOnGracePeriodCompleted($subscriptions),
            new StoreOrderOnPaid($orders, $bindings),
            new StoreOrderOnPaymentFailed($orders, $bindings),
            new CancelOrderOnCanceled($orders),
        ];

        if ($refunds !== null) {
            // Persistence only: SyncRefundOnStatusChange writes the refund row.
            // The order's reversal progress is read live from the API via
            // OrderHandle, so no local order status is synthesized here.
            $reactions[] = new SyncRefundOnStatusChange($refunds, $bindings);
        }

        return new WebhookProcessor(
            eventFactory: new WebhookEventFactory($getOrder, $getSubscription, $getRefund),
            repository: $webhookCalls,
            dispatcher: $dispatcher,
            webhookSecret: $config->getWebhookSecret() ?? '',
            reactions: [
                ...$reactions,
                ...$additionalReactions,
            ],
        );
    }
}
