<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

/**
 * Data for storing a new order from Vatly.
 *
 * @immutable
 */
class StoreOrderData
{
    public function __construct(
        public string $vatlyId,
        public string $customerId,
        public string $status,
        public int $total,
        public string $currency,
        public ?string $invoiceNumber = null,
        public ?string $paymentMethod = null,
    ) {}
}
