<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Events\OrderPaid;

class StoreOrderOnPaid implements WebhookReactionInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {
        //
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
            $this->orders->update($existing, [
                'status' => 'paid',
                'total' => $event->total,
                'currency' => $event->currency,
                'invoice_number' => $event->invoiceNumber,
                'payment_method' => $event->paymentMethod,
            ]);

            return;
        }

        $this->orders->create([
            'vatly_id' => $event->orderId,
            'customer_id' => $event->customerId,
            'status' => 'paid',
            'total' => $event->total,
            'currency' => $event->currency,
            'invoice_number' => $event->invoiceNumber,
            'payment_method' => $event->paymentMethod,
        ]);
    }
}
