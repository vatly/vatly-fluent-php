<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Contracts;

use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Tests\TestCase;

class OrderInterfaceTest extends TestCase
{
    public function test_it_can_be_implemented_and_used(): void
    {
        $order = $this->createMockOrder();

        $this->assertSame('ord_test_123', $order->getVatlyId());
        $this->assertSame('paid', $order->getStatus());
        $this->assertSame('INV-2024-001', $order->getInvoiceNumber());
        $this->assertSame(9900, $order->getTotal());
        $this->assertSame('EUR', $order->getCurrency());
        $this->assertSame('credit_card', $order->getPaymentMethod());
        $this->assertTrue($order->isPaid());
        $this->assertInstanceOf(BillableInterface::class, $order->getOwner());
    }

    private function createMockOrder(): OrderInterface
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

                    public function save(): mixed
                    {
                        return true;
                    }
                };
            }

            public function isPaid(): bool
            {
                return true;
            }
        };
    }
}
