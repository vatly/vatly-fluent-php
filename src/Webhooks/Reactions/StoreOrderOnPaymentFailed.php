<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;

/**
 * Mirrors {@see StoreOrderOnPaid}: ensures the local order row reflects the
 * upstream order state after an `order.payment_failed` webhook. The persisted status
 * is whatever the signed Order payload reports (typically
 * `pending` during dunning) — we deliberately don't synthesise a
 * driver-specific status like `'failed'`, so `OrderInterface::getStatus()`
 * stays a faithful mirror of upstream.
 *
 * @immutable
 */
class StoreOrderOnPaymentFailed implements WebhookReactionInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CustomerBindingRepository $bindings,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof OrderPaymentFailed;
    }

    public function handle(object $event): void
    {
        /** @var OrderPaymentFailed $event */
        $existing = $this->orders->findByVatlyId($event->orderId);

        if ($existing !== null) {
            $this->orders->update($existing, new UpdateOrderData(
                status: $event->status,
                // Flatten Money → integer cents at the persistence edge; the
                // currency now travels on the Money value object.
                total: $event->total->toCents(),
                currency: $event->total->currency,
                invoiceNumber: $event->invoiceNumber,
                paymentMethod: $event->paymentMethod,
                subtotal: $event->subtotal->toCents(),
                taxSummary: $event->taxSummary,
                metadata: $event->metadata,
            ));

            return;
        }

        // Order may be unattributed (no Vatly customer id) — skip bindings rather
        // than write/lookup against an empty string, which would either create an
        // invalid binding row or trip a non-empty-id constraint downstream.
        $hostCustomerId = null;
        if ($event->customerId !== '') {
            $hostCustomerId = $this->bindings->hostCustomerIdFor($event->customerId);
            $this->bindings->record($event->customerId);
        }

        $this->orders->store(new StoreOrderData(
            vatlyId: $event->orderId,
            customerId: $event->customerId,
            status: $event->status,
            total: $event->total->toCents(),
            currency: $event->total->currency,
            invoiceNumber: $event->invoiceNumber,
            paymentMethod: $event->paymentMethod,
            subtotal: $event->subtotal->toCents(),
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
            metadata: $event->metadata,
        ));
    }
}
