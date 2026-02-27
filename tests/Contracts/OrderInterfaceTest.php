<?php

declare(strict_types=1);

use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\OrderInterface;

test('it can be implemented and used', function () {
    $order = createMockOrder();

    expect($order->getVatlyId())->toBe('ord_test_123')
        ->and($order->getStatus())->toBe('paid')
        ->and($order->getInvoiceNumber())->toBe('INV-2024-001')
        ->and($order->getTotal())->toBe(9900)
        ->and($order->getCurrency())->toBe('EUR')
        ->and($order->getPaymentMethod())->toBe('credit_card')
        ->and($order->isPaid())->toBeTrue()
        ->and($order->getOwner())->toBeInstanceOf(BillableInterface::class);
});

function createMockOrder(): OrderInterface
{
    return new class implements OrderInterface {
        public function getVatlyId(): string
        {
            return 'ord_test_123';
        }

        public function getStatus(): string
        {
            return 'paid';
        }

        public function getInvoiceNumber(): ?string
        {
            return 'INV-2024-001';
        }

        public function getTotal(): int
        {
            return 9900;
        }

        public function getCurrency(): string
        {
            return 'EUR';
        }

        public function getPaymentMethod(): ?string
        {
            return 'credit_card';
        }

        public function getOwner(): BillableInterface
        {
            return new class implements BillableInterface {
                public function getVatlyId(): ?string
                {
                    return 'cus_123';
                }

                public function setVatlyId(string $id): void {}

                public function hasVatlyId(): bool
                {
                    return true;
                }

                public function getVatlyEmail(): ?string
                {
                    return 'test@example.com';
                }

                public function getVatlyName(): ?string
                {
                    return 'Test User';
                }

                public function getKey(): string|int
                {
                    return 1;
                }

                public function save(): void {}
            };
        }

        public function isPaid(): bool
        {
            return true;
        }
    };
}
