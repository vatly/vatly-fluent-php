<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreChargebackData;
use Vatly\Fluent\Data\UpdateChargebackData;
use Vatly\API\Webhooks\Events\OrderChargebackReceived;
use Vatly\API\Webhooks\Events\OrderChargebackReversed;

/**
 * Persists `order.chargeback_*` webhooks onto the driver's local chargeback row
 * — the chargeback mirror of {@see SyncRefundOnStatusChange}.
 *
 * Store-or-update: a chargeback seen for the first time (`chargeback_received`)
 * is stored, resolving the host customer via bindings; a subsequent
 * `chargeback_reversed` for the same chargeback updates the existing row's
 * status/totals rather than inserting a duplicate. Registered only when a
 * {@see ChargebackRepositoryInterface} is wired — without one, the typed
 * chargeback events are still dispatched for drivers to handle.
 *
 * Persistence is only meaningful when the events are enriched (a `GetChargeback`
 * action is wired), since the sparse webhook payload carries no customer id,
 * status, or tax breakdown.
 *
 * @immutable
 */
class SyncChargebackOnStatusChange implements WebhookReactionInterface
{
    public function __construct(
        private ChargebackRepositoryInterface $chargebacks,
        private CustomerBindingRepository $bindings,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof OrderChargebackReceived
            || $event instanceof OrderChargebackReversed;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof OrderChargebackReceived
            && ! $event instanceof OrderChargebackReversed) {
            return;
        }

        $existing = $this->chargebacks->findByVatlyId($event->chargebackId);

        if ($existing !== null) {
            $this->chargebacks->update($existing, new UpdateChargebackData(
                status: $event->status,
                total: $event->total,
                currency: $event->currency,
                subtotal: $event->subtotal,
                taxSummary: $event->taxSummary,
                reason: $event->reason,
            ));

            return;
        }

        $hostCustomerId = null;
        if ($event->customerId !== '') {
            $hostCustomerId = $this->bindings->hostCustomerIdFor($event->customerId);
            $this->bindings->record($event->customerId);
        }

        $this->chargebacks->store(new StoreChargebackData(
            vatlyId: $event->chargebackId,
            customerId: $event->customerId,
            status: $event->status,
            total: $event->total,
            currency: $event->currency,
            originalOrderId: $event->originalOrderId,
            reason: $event->reason,
            subtotal: $event->subtotal,
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
        ));
    }
}
