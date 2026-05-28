<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\PaymentFailed;

/**
 * Mirrors {@see StoreOrderOnPaid}: ensures the local order row reflects the
 * upstream `payment.failed` outcome (status `'failed'`). Without this, a
 * driver using the fixed wiring would leave previously-paid renewal orders
 * looking paid locally, and never store a first-time order that fails.
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
        return $event instanceof PaymentFailed;
    }

    public function handle(object $event): void
    {
        /** @var PaymentFailed $event */
        $existing = $this->orders->findByVatlyId($event->orderId);

        if ($existing !== null) {
            $this->orders->update($existing, new UpdateOrderData(
                status: 'failed',
                total: $event->total,
                currency: $event->currency,
                invoiceNumber: $event->invoiceNumber,
                paymentMethod: $event->paymentMethod,
                subtotal: $event->subtotal,
                taxSummary: $event->taxSummary,
                metadata: $event->metadata,
            ));

            return;
        }

        $hostCustomerId = $this->bindings->hostCustomerIdFor($event->customerId);
        $this->bindings->record($event->customerId);

        $this->orders->store(new StoreOrderData(
            vatlyId: $event->orderId,
            customerId: $event->customerId,
            status: 'failed',
            total: $event->total,
            currency: $event->currency,
            invoiceNumber: $event->invoiceNumber,
            paymentMethod: $event->paymentMethod,
            subtotal: $event->subtotal,
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
            metadata: $event->metadata,
        ));
    }
}
