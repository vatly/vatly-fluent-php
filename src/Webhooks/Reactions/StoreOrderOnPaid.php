<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderWasCreatedFromWebhook;
use Vatly\API\Webhooks\Events\OrderPaid;

/**
 * @immutable
 */
class StoreOrderOnPaid implements WebhookReactionInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CustomerBindingRepository $bindings,
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof OrderPaid;
    }

    public function handle(object $event): void
    {
        /** @var OrderPaid $event */
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

        $order = $this->orders->store(new StoreOrderData(
            vatlyId: $event->orderId,
            customerId: $event->customerId,
            status: $event->status,
            total: $event->total->toCents(),
            currency: $event->total->currency,
            invoiceNumber: $event->invoiceNumber,
            paymentMethod: $event->paymentMethod,
            subtotal: $event->subtotal->toCents(),
            taxSummary: $event->taxSummary,
            testmode: $event->testmode,
            hostCustomerId: $hostCustomerId,
            metadata: $event->metadata,
            // Lines are immutable once paid, so they're only written on the
            // initial store — the update path above leaves existing lines as-is.
            lines: $event->lines,
        ));

        // Driver may legitimately decline to persist (store() returns null) —
        // only announce the local order when a row was actually created. This
        // fires exactly once per brand-new order (not on the update path above),
        // mirroring SyncSubscriptionOnStarted.
        if ($order === null) {
            return;
        }

        $this->dispatcher->dispatch(new OrderWasCreatedFromWebhook($order));
    }
}
