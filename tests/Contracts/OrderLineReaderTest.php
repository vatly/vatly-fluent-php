<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Contracts;

use Vatly\Fluent\Contracts\OrderLineInterface;
use Vatly\Fluent\Contracts\OrderLineReader;
use Vatly\Fluent\Tests\TestCase;

/**
 * Contract-level test for {@see OrderLineReader} semantics, using an in-memory
 * repository double. `listForSubscription` is the load-bearing query: it
 * returns lines whose `subscriptionId` matches (the initial signup line and
 * renewals), plus legacy renewal lines without one whose
 * `productType === 'subscription'` and `productId === $subscriptionId`. That is
 * how a driver reaches the orders a subscription generated (initial +
 * renewals).
 */
class OrderLineReaderTest extends TestCase
{
    public function test_list_for_order_returns_only_lines_of_that_order(): void
    {
        $reader = $this->makeReader([
            $this->line('order_item_1', 'ord_1', 'subscription', 'subscription_a'),
            $this->line('order_item_2', 'ord_1', 'one_off_product', 'product_x'),
            $this->line('order_item_3', 'ord_2', 'subscription', 'subscription_a'),
        ]);

        $lines = $reader->listForOrder('ord_1');

        $this->assertCount(2, $lines);
        $this->assertSame(['order_item_1', 'order_item_2'], array_map(
            fn (OrderLineInterface $l) => $l->getVatlyId(),
            $lines,
        ));
    }

    public function test_list_for_subscription_includes_the_initial_order_and_renewals(): void
    {
        $reader = $this->makeReader([
            // Initial signup line: references the plan, linked only by subscriptionId.
            $this->line('order_item_1', 'ord_1', 'subscription_plan', 'subscription_plan_pro', 'subscription_a'),
            // A one-off line in the same order that happens to share the id — must NOT match.
            $this->line('order_item_2', 'ord_1', 'one_off_product', 'subscription_a'),
            // Renewal carrying the link: matches both rules but is returned once.
            $this->line('order_item_3', 'ord_2', 'subscription', 'subscription_a', 'subscription_a'),
            // Renewal stored before Vatly carried the link.
            $this->line('order_item_4', 'ord_3', 'subscription', 'subscription_a'),
            // Another customer's signup for the same plan — must NOT match.
            $this->line('order_item_5', 'ord_4', 'subscription_plan', 'subscription_plan_pro', 'subscription_b'),
            // A signup whose subscription was not yet available — must NOT match.
            $this->line('order_item_6', 'ord_5', 'subscription_plan', 'subscription_plan_pro'),
            // A different subscription's renewal — must NOT match.
            $this->line('order_item_7', 'ord_6', 'subscription', 'subscription_b', 'subscription_b'),
        ]);

        $lines = $reader->listForSubscription('subscription_a');

        $this->assertSame(['order_item_1', 'order_item_3', 'order_item_4'], array_map(
            fn (OrderLineInterface $l) => $l->getVatlyId(),
            $lines,
        ));
    }

    public function test_list_for_subscription_is_empty_when_nothing_matches(): void
    {
        $reader = $this->makeReader([
            $this->line('order_item_1', 'ord_1', 'one_off_product', 'product_x'),
        ]);

        $this->assertSame([], $reader->listForSubscription('subscription_a'));
    }

    /**
     * @param OrderLineInterface[] $lines
     */
    private function makeReader(array $lines): OrderLineReader
    {
        return new class($lines) implements OrderLineReader {
            /**
             * @param OrderLineInterface[] $lines
             */
            public function __construct(private array $lines) {}

            public function listForOrder(string $vatlyOrderId): array
            {
                return array_values(array_filter(
                    $this->lines,
                    fn (OrderLineInterface $l) => $l->getOrderId() === $vatlyOrderId,
                ));
            }

            public function listForSubscription(string $subscriptionId): array
            {
                return array_values(array_filter(
                    $this->lines,
                    fn (OrderLineInterface $l) => $l->getSubscriptionId() === $subscriptionId
                        || ($l->getProductType() === 'subscription' && $l->getProductId() === $subscriptionId),
                ));
            }
        };
    }

    private function line(string $id, string $orderId, ?string $productType, ?string $productId, ?string $subscriptionId = null): OrderLineInterface
    {
        return new class($id, $orderId, $productType, $productId, $subscriptionId) implements OrderLineInterface {
            public function __construct(
                private string $id,
                private string $orderId,
                private ?string $productType,
                private ?string $productId,
                private ?string $subscriptionId,
            ) {}

            public function getVatlyId(): string
            {
                return $this->id;
            }

            public function getOrderId(): string
            {
                return $this->orderId;
            }

            public function getDescription(): string
            {
                return 'Line '.$this->id;
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
                return $this->productType;
            }

            public function getProductId(): ?string
            {
                return $this->productId;
            }

            public function getSubscriptionId(): ?string
            {
                return $this->subscriptionId;
            }
        };
    }
}
