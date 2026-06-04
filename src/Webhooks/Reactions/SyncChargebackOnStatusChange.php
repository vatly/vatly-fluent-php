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
 * The events carry the full customer id, status, and tax breakdown — the
 * webhook payload is the fat, signed resource snapshot — so the stored row is
 * persistence-grade without any follow-up API call.
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
                // Chargeback events carry nullable Money — flatten when present,
                // otherwise leave the field untouched (UpdateChargebackData
                // treats null as "no change"). Currency is still a standalone
                // field on these DTOs, so read it directly off the event.
                total: $event->total?->toCents(),
                currency: $event->currency,
                subtotal: $event->subtotal?->toCents(),
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
            // StoreChargebackData::$total is a non-null int; nullable Money
            // flattens to 0 when the sparse (un-enriched) webhook carried no
            // amount. Currency stays a standalone event field here.
            total: $event->total?->toCents() ?? 0,
            currency: $event->currency,
            originalOrderId: $event->originalOrderId,
            testmode: $event->testmode,
            reason: $event->reason,
            subtotal: $event->subtotal?->toCents(),
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
        ));
    }
}
