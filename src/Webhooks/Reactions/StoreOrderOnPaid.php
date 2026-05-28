<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderPaid;

/**
 * @immutable
 */
class StoreOrderOnPaid implements WebhookReactionInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CustomerBindingRepository $bindings,
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
                status: 'paid',
                total: $event->total,
                currency: $event->currency,
                invoiceNumber: $event->invoiceNumber,
                paymentMethod: $event->paymentMethod,
                subtotal: $event->subtotal,
                taxSummary: $event->taxSummary,
            ));

            return;
        }

        $hostCustomerId = $this->bindings->hostCustomerIdFor($event->customerId);
        $this->bindings->record($event->customerId);

        $this->orders->store(new StoreOrderData(
            vatlyId: $event->orderId,
            customerId: $event->customerId,
            status: 'paid',
            total: $event->total,
            currency: $event->currency,
            invoiceNumber: $event->invoiceNumber,
            paymentMethod: $event->paymentMethod,
            subtotal: $event->subtotal,
            taxSummary: $event->taxSummary,
            hostCustomerId: $hostCustomerId,
        ));
    }
}
