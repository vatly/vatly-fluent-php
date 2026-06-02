<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Contracts;

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
        $this->assertSame(8182, $order->getSubtotal());
        $this->assertSame('EUR', $order->getCurrency());
        $this->assertSame('credit_card', $order->getPaymentMethod());
        $this->assertTrue($order->isPaid());
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

            public function getSubtotal(): ?int
            {
                return 8182;
            }

            public function getCurrency(): string
            {
                return 'EUR';
            }

            public function getPaymentMethod(): ?string
            {
                return 'credit_card';
            }

            public function isPaid(): bool
            {
                return true;
            }
        };
    }
}
