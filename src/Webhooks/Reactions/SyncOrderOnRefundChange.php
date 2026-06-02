<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\RefundReader;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\RefundCanceled;
use Vatly\Fluent\Events\RefundCompleted;
use Vatly\Fluent\Types\LocalOrderStatus;

/**
 * Propagates a refund's terminal state onto the original order's *local*
 * status — the consumer-side mirror of vatlify's own
 * `UpdateOrderContext::handleRefundWasCompleted`.
 *
 * Vatly never emits an `order.refunded` webhook and its public Order resource
 * always reports `paid` (refund/chargeback states are collapsed upstream). But
 * the `refund.completed` / `refund.canceled` payloads carry the original order
 * id plus the refunded subtotal, which is everything needed to derive the new
 * status from local state — no API re-fetch.
 *
 * This reaction MUST run after {@see SyncRefundOnStatusChange}, which writes the
 * refund row first; this one then reads the cumulative completed subtotal back
 * out via {@see RefundReader::sumSubtotalsForOrder()} and compares it to the
 * order's own subtotal:
 *
 *   - cumulative <= 0                       → {@see LocalOrderStatus::PAID} (fully backed out)
 *   - cumulative >= order subtotal          → {@see LocalOrderStatus::REFUNDED}
 *   - otherwise                             → {@see LocalOrderStatus::PARTIALLY_REFUNDED}
 *
 * Handling both events symmetrically means a `refund.canceled` that drops the
 * cumulative back below the order subtotal reverts the status
 * (`refunded` → `partially_refunded` → `paid`).
 *
 * Registered only when both an {@see OrderRepositoryInterface} and a refund
 * repository are wired — without local order + refund state there is nothing to
 * reconcile. The typed refund events still dispatch regardless.
 *
 * @immutable
 */
class SyncOrderOnRefundChange implements WebhookReactionInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private RefundReader $refunds,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof RefundCompleted
            || $event instanceof RefundCanceled;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof RefundCompleted && ! $event instanceof RefundCanceled) {
            return;
        }

        $order = $this->orders->findByVatlyId($event->originalOrderId);

        if ($order === null) {
            // The original order isn't tracked locally (e.g. unattributed, or
            // this consumer doesn't persist the order it was refunded against).
            // Nothing to reconcile — the refund row itself is still recorded by
            // SyncRefundOnStatusChange.
            return;
        }

        $cumulativeRefundedSubtotal = $this->refunds->sumSubtotalsForOrder($event->originalOrderId);

        $this->orders->update($order, new UpdateOrderData(
            status: $this->deriveStatus($cumulativeRefundedSubtotal, $order->getSubtotal()),
        ));
    }

    /**
     * Mirror of vatlify's enum semantics: a fully-refunded order matches its
     * subtotal exactly; anything in between is partial. When the local order
     * has no recorded subtotal we cannot prove a full refund, so we stay on the
     * conservative "partially refunded" instead of over-claiming.
     */
    private function deriveStatus(int $cumulativeRefundedSubtotal, ?int $orderSubtotal): string
    {
        if ($cumulativeRefundedSubtotal <= 0) {
            return LocalOrderStatus::PAID;
        }

        if ($orderSubtotal !== null && $cumulativeRefundedSubtotal >= $orderSubtotal) {
            return LocalOrderStatus::REFUNDED;
        }

        return LocalOrderStatus::PARTIALLY_REFUNDED;
    }
}
