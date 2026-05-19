<?php

declare(strict_types=1);

namespace Vatly\Fluent\Data;

/**
 * Data for updating an existing order from Vatly.
 *
 * @immutable
 */
class UpdateOrderData
{
    public function __construct(
        public ?string $status = null,
        public ?int $total = null,
        public ?string $currency = null,
        public ?string $invoiceNumber = null,
        public ?string $paymentMethod = null,
    ) {}
}
