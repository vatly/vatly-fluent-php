<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing an order being paid at Vatly.
 */
class OrderPaid
{
    public const VATLY_EVENT_NAME = 'order.paid';

    public function __construct(
        public readonly string $customerId,
        public readonly string $orderId,
        public readonly int $total,
        public readonly string $currency,
        public readonly ?string $invoiceNumber,
        public readonly ?string $paymentMethod,
    ) {
        //
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            customerId: $webhook->object['data']['customerId'],
            orderId: $webhook->resourceId,
            total: $webhook->object['data']['total'],
            currency: $webhook->object['data']['currency'],
            invoiceNumber: $webhook->object['data']['invoiceNumber'] ?? null,
            paymentMethod: $webhook->object['data']['paymentMethod'] ?? null,
        );
    }
}
