<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

class OrderPaid
{
    public const VATLY_EVENT_NAME = 'order.paid';

    public string $customerId;
    public string $orderId;
    public int $total;
    public string $currency;
    public ?string $invoiceNumber;
    public ?string $paymentMethod;

    public function __construct(string $customerId, string $orderId, int $total, string $currency, ?string $invoiceNumber, ?string $paymentMethod)
    {
        $this->customerId = $customerId;
        $this->orderId = $orderId;
        $this->total = $total;
        $this->currency = $currency;
        $this->invoiceNumber = $invoiceNumber;
        $this->paymentMethod = $paymentMethod;
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            $webhook->object['data']['customerId'],
            $webhook->resourceId,
            $webhook->object['data']['total'],
            $webhook->object['data']['currency'],
            $webhook->object['data']['invoiceNumber'] ?? null,
            $webhook->object['data']['paymentMethod'] ?? null
        );
    }
}
