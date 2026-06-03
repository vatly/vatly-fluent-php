<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Contracts;

use Vatly\Fluent\Contracts\OrderLineInterface;
use Vatly\Fluent\Tests\TestCase;

class OrderLineInterfaceTest extends TestCase
{
    public function test_it_can_be_implemented_and_used(): void
    {
        $line = $this->createMockLine();

        $this->assertSame('order_item_123', $line->getVatlyId());
        $this->assertSame('ord_456', $line->getOrderId());
        $this->assertSame('Pro plan — monthly', $line->getDescription());
        $this->assertSame(1, $line->getQuantity());
        $this->assertSame(2000, $line->getBasePrice());
        $this->assertSame(2420, $line->getTotal());
        $this->assertSame(2000, $line->getSubtotal());
        $this->assertSame('subscription', $line->getProductType());
        $this->assertSame('subscription_abc', $line->getProductId());
    }

    public function test_product_fields_may_be_null(): void
    {
        $line = new class implements OrderLineInterface {
            public function getVatlyId(): string
            {
                return 'order_item_legacy';
            }

            public function getOrderId(): string
            {
                return 'ord_456';
            }

            public function getDescription(): string
            {
                return 'Legacy line';
            }

            public function getQuantity(): int
            {
                return 1;
            }

            public function getBasePrice(): int
            {
                return 1000;
            }

            public function getTotal(): int
            {
                return 1000;
            }

            public function getSubtotal(): int
            {
                return 1000;
            }

            public function getProductType(): ?string
            {
                return null;
            }

            public function getProductId(): ?string
            {
                return null;
            }
        };

        $this->assertNull($line->getProductType());
        $this->assertNull($line->getProductId());
    }

    private function createMockLine(): OrderLineInterface
    {
        return new class implements OrderLineInterface {
            public function getVatlyId(): string
            {
                return 'order_item_123';
            }

            public function getOrderId(): string
            {
                return 'ord_456';
            }

            public function getDescription(): string
            {
                return 'Pro plan — monthly';
            }

            public function getQuantity(): int
            {
                return 1;
            }

            public function getBasePrice(): int
            {
                return 2000;
            }

            public function getTotal(): int
            {
                return 2420;
            }

            public function getSubtotal(): int
            {
                return 2000;
            }

            public function getProductType(): ?string
            {
                return 'subscription';
            }

            public function getProductId(): ?string
            {
                return 'subscription_abc';
            }
        };
    }
}
