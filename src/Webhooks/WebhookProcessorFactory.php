<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use Vatly\API\VatlyApiClient;
use Vatly\API\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
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
use Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange;
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
     * The `apiClient` is threaded so the {@see WebhookEventFactory} can hydrate
     * the money/tax-bearing events from the signed webhook payload — no follow-up
     * API GET is made; the payload is the authoritative snapshot.
     *
     * `refunds` and `chargebacks` are optional and back-compatible: a driver
     * that only wires subscriptions/orders can keep calling this without them.
     * The `refund.*` and `order.chargeback_*` events are always built from the
     * (fat) payload; the opt-in repositories only gate whether the matching
     * persistence reaction ({@see SyncRefundOnStatusChange} /
     * {@see SyncChargebackOnStatusChange}) is registered.
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
        VatlyApiClient $apiClient,
        ?RefundRepositoryInterface $refunds = null,
        ?ChargebackRepositoryInterface $chargebacks = null,
        array $additionalReactions = [],
    ): WebhookProcessor {
        $reactions = [
            new SyncSubscriptionOnStarted($subscriptions, $bindings, $dispatcher),
            new SyncSubscriptionOnBillingUpdated($subscriptions),
            new ResumeSubscriptionOnResumed($subscriptions),
            new CancelSubscriptionOnCanceled($subscriptions),
            new EndSubscriptionOnGracePeriodCompleted($subscriptions),
            new StoreOrderOnPaid($orders, $bindings, $dispatcher),
            new StoreOrderOnPaymentFailed($orders, $bindings),
            new CancelOrderOnCanceled($orders),
        ];

        if ($refunds !== null) {
            // Persistence only: SyncRefundOnStatusChange writes the refund row.
            // The order's reversal progress is read live from the API via
            // OrderHandle, so no local order status is synthesized here.
            $reactions[] = new SyncRefundOnStatusChange($refunds, $bindings);
        }

        if ($chargebacks !== null) {
            // Persistence only: SyncChargebackOnStatusChange writes the
            // chargeback row. The order's reversal progress (incl. chargebacks)
            // is read live from the API via OrderHandle, so no local order
            // status is synthesized here.
            $reactions[] = new SyncChargebackOnStatusChange($chargebacks, $bindings);
        }

        return new WebhookProcessor(
            eventFactory: new WebhookEventFactory($apiClient),
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
