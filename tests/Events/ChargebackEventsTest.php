<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use Mockery;
use Vatly\API\Resources\Chargeback as ApiChargeback;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Events\OrderChargebackReceived;
use Vatly\Fluent\Events\OrderChargebackReversed;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;

class ChargebackEventsTest extends TestCase
{
    public function test_received_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.chargeback_received', OrderChargebackReceived::VATLY_EVENT_NAME);
    }

    public function test_reversed_has_correct_vatly_event_name_constant(): void
    {
        $this->assertSame('order.chargeback_reversed', OrderChargebackReversed::VATLY_EVENT_NAME);
    }

    public function test_received_creates_from_webhook_with_order_id_from_entity_id(): void
    {
        $event = OrderChargebackReceived::fromWebhook($this->webhook('order.chargeback_received', [
            'id' => 'chargeback_789',
            'resource' => 'chargeback',
            'originalOrderId' => 'ord_original_1',
            'reason' => 'fraudulent',
        ]));

        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame('fraudulent', $event->reason);
    }

    public function test_reversed_creates_from_webhook_with_null_reason_when_absent(): void
    {
        $event = OrderChargebackReversed::fromWebhook($this->webhook('order.chargeback_reversed', [
            'id' => 'chargeback_789',
            'resource' => 'chargeback',
            'originalOrderId' => 'ord_original_1',
        ]));

        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertNull($event->reason);
    }

    public function test_original_order_id_falls_back_to_entity_id_when_absent(): void
    {
        $event = OrderChargebackReceived::fromWebhook($this->webhook('order.chargeback_received', [
            'id' => 'chargeback_789',
        ]));

        $this->assertSame('ord_123', $event->originalOrderId);
    }

    public function test_received_enriches_from_api_chargeback(): void
    {
        $event = OrderChargebackReceived::fromApiChargeback($this->apiChargeback('pending'));

        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame('ord_original_1', $event->orderId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('pending', $event->status);
        $this->assertSame(9900, $event->total);
        $this->assertSame(8182, $event->subtotal);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('fraudulent', $event->reason);
        $this->assertCount(1, $event->taxSummary);
        $this->assertSame('VAT', $event->taxSummary->items[0]->rate->name);
        $this->assertSame(1718, $event->taxSummary->items[0]->amount);
    }

    public function test_reversed_enriches_from_api_chargeback(): void
    {
        $event = OrderChargebackReversed::fromApiChargeback($this->apiChargeback('won'));

        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('won', $event->status);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame(9900, $event->total);
    }

    private function apiChargeback(string $status): ApiChargeback
    {
        $chargeback = new ApiChargeback(Mockery::mock(VatlyApiClient::class));
        $chargeback->id = 'chargeback_789';
        $chargeback->customerId = 'cus_456';
        $chargeback->status = $status;
        $chargeback->reason = 'fraudulent';
        $chargeback->originalOrderId = 'ord_original_1';
        $chargeback->total = new Money('EUR', '99.00');
        $chargeback->subtotal = new Money('EUR', '81.82');
        $chargeback->taxSummary = new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '17.18'],
            ],
        ]);

        return $chargeback;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function webhook(string $eventName, array $object): WebhookReceived
    {
        return new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: $eventName,
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: $object,
        );
    }
}
