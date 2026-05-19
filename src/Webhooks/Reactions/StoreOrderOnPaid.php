<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderPaid;

class StoreOrderOnPaid implements WebhookReactionInterface
{
    private OrderRepositoryInterface $orders;

    public function __construct(OrderRepositoryInterface $orders)
    {
        $this->orders = $orders;
    }

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
            ));

            return;
        }

        $this->orders->store(new StoreOrderData(
            vatlyId: $event->orderId,
            customerId: $event->customerId,
            status: 'paid',
            total: $event->total,
            currency: $event->currency,
            invoiceNumber: $event->invoiceNumber,
            paymentMethod: $event->paymentMethod,
        ));
    }
}
