<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Mockery;
use Vatly\API\Resources\Refund as ApiRefund;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Events\RefundCanceled;
use Vatly\Fluent\Events\RefundCompleted;
use Vatly\Fluent\Events\RefundFailed;
use Vatly\Fluent\Tests\TestCase;

class RefundEventsTest extends TestCase
{
    public function test_event_name_constants(): void
    {
        $this->assertSame('refund.completed', RefundCompleted::VATLY_EVENT_NAME);
        $this->assertSame('refund.failed', RefundFailed::VATLY_EVENT_NAME);
        $this->assertSame('refund.canceled', RefundCanceled::VATLY_EVENT_NAME);
    }

    public function test_completed_maps_api_refund_to_cents_and_tax_breakdown(): void
    {
        $event = RefundCompleted::fromApiRefund($this->apiRefund('refunded'));

        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('refund_123', $event->refundId);
        $this->assertSame('refunded', $event->status);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame(9900, $event->total);
        $this->assertSame(8182, $event->subtotal);
        $this->assertSame('EUR', $event->currency);
        $this->assertCount(1, $event->taxSummary);
        $this->assertSame('VAT', $event->taxSummary->items[0]->rate->name);
        $this->assertSame(1718, $event->taxSummary->items[0]->amount);
    }

    public function test_failed_carries_failed_status(): void
    {
        $event = RefundFailed::fromApiRefund($this->apiRefund('failed'));

        $this->assertSame('failed', $event->status);
        $this->assertSame(9900, $event->total);
    }

    public function test_canceled_carries_canceled_status(): void
    {
        $event = RefundCanceled::fromApiRefund($this->apiRefund('canceled'));

        $this->assertSame('canceled', $event->status);
    }

    private function apiRefund(string $status): ApiRefund
    {
        $refund = new ApiRefund(Mockery::mock(VatlyApiClient::class));
        $refund->id = 'refund_123';
        $refund->customerId = 'cus_456';
        $refund->status = $status;
        $refund->originalOrderId = 'ord_original_1';
        $refund->total = new Money('EUR', '99.00');
        $refund->subtotal = new Money('EUR', '81.82');
        $refund->taxSummary = new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '17.18'],
            ],
        ]);

        return $refund;
    }
}
