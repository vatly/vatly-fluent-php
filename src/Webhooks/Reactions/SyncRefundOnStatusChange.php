<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\RefundRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreRefundData;
use Vatly\Fluent\Data\UpdateRefundData;
use Vatly\API\Webhooks\Events\RefundCanceled;
use Vatly\API\Webhooks\Events\RefundCompleted;
use Vatly\API\Webhooks\Events\RefundFailed;

/**
 * Persists `refund.*` webhooks onto the driver's local refund row — the piece
 * that unblocks terminal-state refund reconciliation for consumers that record
 * refunds in a pending state when they're initiated.
 *
 * Store-or-update, mirroring {@see StoreOrderOnPaid}: if the refund is already
 * tracked locally it's updated with the new status/totals; otherwise it's
 * stored (resolving the host customer via bindings). Registered only when a
 * {@see RefundRepositoryInterface} is wired — without one, the typed refund
 * events are still dispatched for drivers to handle.
 *
 * @immutable
 */
class SyncRefundOnStatusChange implements WebhookReactionInterface
{
    public function __construct(
        private RefundRepositoryInterface $refunds,
        private CustomerBindingRepository $bindings,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof RefundCompleted
            || $event instanceof RefundFailed
            || $event instanceof RefundCanceled;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof RefundCompleted
            && ! $event instanceof RefundFailed
            && ! $event instanceof RefundCanceled) {
            return;
        }

        $existing = $this->refunds->findByVatlyId($event->refundId);

        if ($existing !== null) {
            $this->refunds->update($existing, new UpdateRefundData(
                status: $event->status,
                // Flatten Money → integer cents at the persistence edge; the
                // currency now travels on the Money value object.
                total: $event->total->toCents(),
                currency: $event->total->currency,
                subtotal: $event->subtotal->toCents(),
                taxSummary: $event->taxSummary,
            ));

            return;
        }

        $hostCustomerId = null;
        if ($event->customerId !== '') {
            $hostCustomerId = $this->bindings->hostCustomerIdFor($event->customerId);
            $this->bindings->record($event->customerId);
        }

        $this->refunds->store(new StoreRefundData(
            vatlyId: $event->refundId,
            customerId: $event->customerId,
            status: $event->status,
            total: $event->total->toCents(),
            currency: $event->total->currency,
            originalOrderId: $event->originalOrderId,
            testmode: $event->testmode,
            subtotal: $event->subtotal->toCents(),
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
        ));
    }
}
