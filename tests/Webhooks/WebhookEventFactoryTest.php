<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use DateTimeInterface;
use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Resources\Refund as ApiRefund;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\API\Webhooks\WebhookPayload;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetRefund;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\API\Webhooks\Events\CheckoutCanceled;
use Vatly\API\Webhooks\Events\CheckoutExpired;
use Vatly\API\Webhooks\Events\CheckoutFailed;
use Vatly\API\Webhooks\Events\CheckoutPaid;
use Vatly\API\Webhooks\Events\OrderCanceled;
use Vatly\API\Webhooks\Events\OrderChargebackReceived;
use Vatly\API\Webhooks\Events\OrderChargebackReversed;
use Vatly\API\Webhooks\Events\OrderPaid;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;
use Vatly\API\Webhooks\Events\RefundCanceled;
use Vatly\API\Webhooks\Events\RefundCompleted;
use Vatly\API\Webhooks\Events\RefundFailed;
use Vatly\API\Webhooks\Events\SubscriptionBillingUpdated;
use Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately;
use Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\API\Webhooks\Events\SubscriptionCancellationGracePeriodCompleted;
use Vatly\API\Webhooks\Events\SubscriptionResumed;
use Vatly\API\Webhooks\Events\SubscriptionStarted;
use Vatly\API\Webhooks\Events\UnsupportedWebhookReceived;
use Vatly\API\Webhooks\Events\WebhookReceived;
use Vatly\API\Webhooks\Events\WebhookSetupReceived;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\WebhookEventFactory;

class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;
    private GetOrder $getOrder;
    private GetSubscription $getSubscription;
    private GetRefund $getRefund;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getOrder = Mockery::mock(GetOrder::class);
        $this->getSubscription = Mockery::mock(GetSubscription::class);
        $this->getRefund = Mockery::mock(GetRefund::class);
        $this->factory = new WebhookEventFactory($this->getOrder, $this->getSubscription, $this->getRefund);
    }

    public function test_it_converts_upstream_webhook_payload_into_webhook_received_event(): void
    {
        $payload = new WebhookPayload(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: (object) ['customerId' => 'cus_456'],
        );

        $event = $this->factory->fromPayload($payload);

        $this->assertInstanceOf(WebhookReceived::class, $event);
        $this->assertSame('webhook_event_abc', $event->id);
        $this->assertSame('webhook_event', $event->resource);
        $this->assertSame('subscription.started', $event->eventName);
        $this->assertSame('subscription', $event->entityType);
        $this->assertSame('sub_123', $event->entityId);
        $this->assertFalse($event->testmode);
        $this->assertSame('2024-01-15T10:00:00Z', $event->createdAt);
        $this->assertSame('cus_456', $event->getCustomerId());
    }

    public function test_it_converts_payload_with_null_object_into_empty_array(): void
    {
        $payload = new WebhookPayload(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'unknown.event',
            entityType: 'unknown',
            entityId: 'res_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: null,
        );

        $event = $this->factory->fromPayload($payload);

        $this->assertSame([], $event->object);
    }

    public function test_it_creates_subscription_started_event_from_enriched_api_subscription(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $apiSubscription = $this->makeApiSubscription([
            'id' => 'sub_123',
            'customerId' => 'cus_456',
            'subscriptionPlanId' => 'plan_789',
            'name' => 'Premium Plan',
            'quantity' => 1,
            'mandate' => new Mandate('card', '4242'),
        ]);

        $this->getSubscription->shouldReceive('execute')
            ->with('sub_123')
            ->andReturn($apiSubscription);

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
        $this->assertNotNull($event->mandate);
        $this->assertSame('card', $event->mandate->method);
        $this->assertSame('4242', $event->mandate->maskedIdentifier);
    }

    public function test_subscription_started_falls_back_to_webhook_payload_when_enrichment_fails(): void
    {
        // GetSubscription failure (network blip, rate limit, transient 5xx) must
        // not block the webhook flow. The webhook payload itself carries enough
        // to persist the subscription; mandate stays null and is backfilled on
        // the next sync() or subscription.billing_updated event.
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_transient_fail',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
            ],
        );

        $this->getSubscription->shouldReceive('execute')
            ->andThrow(new \RuntimeException('Transient API failure'));

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_transient_fail', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
        // This payload carries no `mandate`, so the fallback yields null.
        $this->assertNull($event->mandate);
    }

    public function test_subscription_started_fallback_parses_mandate_embedded_in_webhook_payload(): void
    {
        // Enrichment fails, but the webhook payload embeds the mandate inline —
        // the fallback must carry it through rather than drop it.
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_fail_with_mandate',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
                'mandate' => ['method' => 'card', 'maskedIdentifier' => '4242'],
            ],
        );

        $this->getSubscription->shouldReceive('execute')->andThrow(new \RuntimeException('Transient API failure'));

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertNotNull($event->mandate);
        $this->assertSame('card', $event->mandate->method);
        $this->assertSame('4242', $event->mandate->maskedIdentifier);
    }

    public function test_subscription_billing_updated_fallback_parses_mandate_embedded_in_webhook_payload(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_bu',
            resource: 'webhook_event',
            eventName: 'subscription.billing_updated',
            entityType: 'subscription',
            entityId: 'sub_fail_with_mandate',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
                'mandate' => ['method' => 'sepa_debit', 'maskedIdentifier' => 'NL91****4300'],
            ],
        );

        $this->getSubscription->shouldReceive('execute')->andThrow(new \RuntimeException('Transient API failure'));

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionBillingUpdated::class, $event);
        $this->assertNotNull($event->mandate);
        $this->assertSame('sepa_debit', $event->mandate->method);
        $this->assertSame('NL91****4300', $event->mandate->maskedIdentifier);
    }

    public function test_subscription_started_event_carries_null_mandate_when_api_returns_none(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_no_mandate',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $apiSubscription = $this->makeApiSubscription([
            'id' => 'sub_no_mandate',
            'customerId' => 'cus_456',
            'subscriptionPlanId' => 'plan_789',
            'name' => 'Premium Plan',
            'quantity' => 1,
            'mandate' => null,
        ]);

        $this->getSubscription->shouldReceive('execute')->andReturn($apiSubscription);

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertNull($event->mandate);
    }

    public function test_it_creates_subscription_canceled_immediately_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.canceled_immediately',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'endedAt' => '2024-01-15T10:00:00Z',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCanceledImmediately::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }

    public function test_it_creates_subscription_canceled_with_grace_period_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.canceled_with_grace_period',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'endedAt' => '2024-02-15T10:00:00Z',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCanceledWithGracePeriod::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endsAt);
    }

    public function test_it_creates_subscription_billing_updated_event_from_enriched_api_subscription(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.billing_updated',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $apiSubscription = $this->makeApiSubscription([
            'id' => 'sub_123',
            'customerId' => 'cus_456',
            'subscriptionPlanId' => 'plan_789',
            'name' => 'Premium Plan',
            'quantity' => 1,
            'mandate' => new Mandate('sepa_debit', 'NL91****4300'),
        ]);

        $this->getSubscription->shouldReceive('execute')
            ->with('sub_123')
            ->andReturn($apiSubscription);

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionBillingUpdated::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
        $this->assertNotNull($event->mandate);
        $this->assertSame('sepa_debit', $event->mandate->method);
        $this->assertSame('NL91****4300', $event->mandate->maskedIdentifier);
    }

    public function test_subscription_billing_updated_falls_back_to_webhook_payload_when_enrichment_fails(): void
    {
        // Same resilience contract as subscription.started: a GetSubscription
        // blip must not block the webhook. Mandate stays null on the fallback
        // so the reaction makes no mandate change; the next sync() reconciles.
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.billing_updated',
            entityType: 'subscription',
            entityId: 'sub_transient_fail',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
            ],
        );

        $this->getSubscription->shouldReceive('execute')
            ->andThrow(new \RuntimeException('Transient API failure'));

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionBillingUpdated::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_transient_fail', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
        $this->assertNull($event->mandate);
    }

    public function test_it_creates_subscription_resumed_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'subscription.resumed',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionResumed::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }

    public function test_it_creates_order_paid_event_from_webhook_with_enriched_tax_data(): void
    {
        $apiOrder = $this->buildApiOrder([
            'id' => 'ord_123',
            'customerId' => 'cus_456',
            'total' => ['currency' => 'EUR', 'value' => '99.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '81.82'],
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
            'invoiceNumber' => 'INV-2024-001',
            'paymentMethod' => 'credit_card',
        ]);

        $this->getOrder->shouldReceive('execute')
            ->once()
            ->with('ord_123')
            ->andReturn($apiOrder);

        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'order.paid',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'total' => ['currency' => 'EUR', 'value' => '99.00'],
                'invoiceNumber' => 'INV-2024-001',
                'paymentMethod' => 'credit_card',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderPaid::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(9900, $event->total->toCents());
        $this->assertSame(8182, $event->subtotal->toCents());
        $this->assertSame('EUR', $event->total->currency);
        $this->assertSame('INV-2024-001', $event->invoiceNumber);
        $this->assertSame('credit_card', $event->paymentMethod);
        $this->assertCount(1, $event->taxSummary->items);
        $this->assertSame('VAT', $event->taxSummary->items[0]->taxRate->name);
        $this->assertSame(21.0, $event->taxSummary->items[0]->taxRate->percentage);
        $this->assertSame(1718, $event->taxSummary->items[0]->amount->toCents());
        $this->assertSame('EUR', $event->taxSummary->items[0]->amount->currency);
    }

    public function test_order_paid_event_carries_mapped_order_lines_from_the_enriched_order(): void
    {
        $apiOrder = $this->buildApiOrder([
            'id' => 'ord_123',
            'customerId' => 'cus_456',
            'total' => ['currency' => 'EUR', 'value' => '99.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '81.82'],
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
            'invoiceNumber' => 'INV-2024-001',
            'paymentMethod' => 'credit_card',
            'lines' => [
                [
                    'id' => 'order_item_sub',
                    'description' => 'Pro plan',
                    'quantity' => 1,
                    'basePrice' => '20.00',
                    'total' => '24.20',
                    'subtotal' => '20.00',
                    'productType' => 'subscription',
                    'productId' => 'subscription_abc',
                ],
                [
                    'id' => 'order_item_legacy',
                    'description' => 'Unattributed line',
                    'quantity' => 2,
                    'basePrice' => '5.00',
                    'total' => '12.10',
                    'subtotal' => '10.00',
                ],
            ],
        ]);

        $this->getOrder->shouldReceive('execute')->once()->with('ord_123')->andReturn($apiOrder);

        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'order.paid',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderPaid::class, $event);
        $this->assertCount(2, $event->lines);

        $this->assertSame('order_item_sub', $event->lines[0]->vatlyId);
        $this->assertSame(2000, $event->lines[0]->basePrice->toCents());
        $this->assertSame(2420, $event->lines[0]->total->toCents());
        $this->assertSame(2000, $event->lines[0]->subtotal->toCents());
        $this->assertSame('subscription', $event->lines[0]->productType);
        $this->assertSame('subscription_abc', $event->lines[0]->productId);

        $this->assertSame('order_item_legacy', $event->lines[1]->vatlyId);
        $this->assertSame(2, $event->lines[1]->quantity);
        $this->assertNull($event->lines[1]->productType);
        $this->assertNull($event->lines[1]->productId);
    }

    public function test_it_creates_payment_failed_event_from_webhook_with_enriched_order(): void
    {
        $apiOrder = $this->buildApiOrder([
            'id' => 'ord_dunning_1',
            'customerId' => 'cus_456',
            'total' => ['currency' => 'EUR', 'value' => '49.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '40.50'],
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '8.50'],
            ],
            'invoiceNumber' => null,
            'paymentMethod' => 'sepa_direct_debit',
            'status' => 'pending',
        ]);

        $this->getOrder->shouldReceive('execute')
            ->once()
            ->with('ord_dunning_1')
            ->andReturn($apiOrder);

        $webhook = new WebhookReceived(
            id: 'webhook_event_pf',
            resource: 'webhook_event',
            eventName: 'order.payment_failed',
            entityType: 'order',
            entityId: 'ord_dunning_1',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'total' => ['currency' => 'EUR', 'value' => '49.00'],
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderPaymentFailed::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_dunning_1', $event->orderId);
        $this->assertSame('pending', $event->status);
        $this->assertSame(4900, $event->total->toCents());
        $this->assertSame(4050, $event->subtotal->toCents());
        $this->assertSame('EUR', $event->total->currency);
        $this->assertNull($event->invoiceNumber);
        $this->assertSame('sepa_direct_debit', $event->paymentMethod);
        $this->assertCount(1, $event->taxSummary->items);
        $this->assertSame('VAT', $event->taxSummary->items[0]->taxRate->name);
        $this->assertSame(850, $event->taxSummary->items[0]->amount->toCents());
    }

    public function test_it_creates_order_canceled_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_oc',
            resource: 'webhook_event',
            eventName: 'order.canceled',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'status' => 'canceled',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderCanceled::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('canceled', $event->status);
    }

    public function test_it_creates_order_chargeback_received_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_cb',
            resource: 'webhook_event',
            eventName: 'order.chargeback_received',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'id' => 'chargeback_789',
                'resource' => 'chargeback',
                'originalOrderId' => 'ord_original_1',
                'reason' => 'fraudulent',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderChargebackReceived::class, $event);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame('fraudulent', $event->reason);
    }

    public function test_it_creates_order_chargeback_reversed_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_cbr',
            resource: 'webhook_event',
            eventName: 'order.chargeback_reversed',
            entityType: 'order',
            entityId: 'ord_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'id' => 'chargeback_789',
                'resource' => 'chargeback',
                'originalOrderId' => 'ord_original_1',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderChargebackReversed::class, $event);
        $this->assertSame('ord_123', $event->orderId);
        $this->assertSame('chargeback_789', $event->chargebackId);
        $this->assertNull($event->reason);
    }

    public function test_it_creates_refund_completed_event_from_enriched_api_refund(): void
    {
        $apiRefund = $this->buildApiRefund([
            'id' => 'refund_123',
            'customerId' => 'cus_456',
            'status' => 'refunded',
            'originalOrderId' => 'ord_original_1',
            'total' => ['currency' => 'EUR', 'value' => '99.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '81.82'],
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]);

        $this->getRefund->shouldReceive('execute')
            ->once()
            ->with('refund_123')
            ->andReturn($apiRefund);

        $webhook = new WebhookReceived(
            id: 'webhook_event_rf',
            resource: 'webhook_event',
            eventName: 'refund.completed',
            entityType: 'refund',
            entityId: 'refund_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(RefundCompleted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('refund_123', $event->refundId);
        $this->assertSame('refunded', $event->status);
        $this->assertSame('ord_original_1', $event->originalOrderId);
        $this->assertSame(9900, $event->total->toCents());
        $this->assertSame(8182, $event->subtotal->toCents());
        $this->assertSame('EUR', $event->total->currency);
        $this->assertCount(1, $event->taxSummary->items);
        $this->assertSame('VAT', $event->taxSummary->items[0]->taxRate->name);
        $this->assertSame(1718, $event->taxSummary->items[0]->amount->toCents());
    }

    public function test_it_creates_refund_failed_event_from_enriched_api_refund(): void
    {
        $apiRefund = $this->buildApiRefund([
            'id' => 'refund_failed_1',
            'customerId' => 'cus_456',
            'status' => 'failed',
            'originalOrderId' => 'ord_original_1',
            'total' => ['currency' => 'EUR', 'value' => '49.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '40.50'],
            'taxRates' => [],
        ]);

        $this->getRefund->shouldReceive('execute')->once()->with('refund_failed_1')->andReturn($apiRefund);

        $webhook = new WebhookReceived(
            id: 'webhook_event_rff',
            resource: 'webhook_event',
            eventName: 'refund.failed',
            entityType: 'refund',
            entityId: 'refund_failed_1',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(RefundFailed::class, $event);
        $this->assertSame('refund_failed_1', $event->refundId);
        $this->assertSame('failed', $event->status);
        $this->assertSame(4900, $event->total->toCents());
    }

    public function test_it_creates_refund_canceled_event_from_enriched_api_refund(): void
    {
        $apiRefund = $this->buildApiRefund([
            'id' => 'refund_canceled_1',
            'customerId' => 'cus_456',
            'status' => 'canceled',
            'originalOrderId' => 'ord_original_1',
            'total' => ['currency' => 'EUR', 'value' => '10.00'],
            'subtotal' => ['currency' => 'EUR', 'value' => '8.26'],
            'taxRates' => [],
        ]);

        $this->getRefund->shouldReceive('execute')->once()->with('refund_canceled_1')->andReturn($apiRefund);

        $webhook = new WebhookReceived(
            id: 'webhook_event_rfc',
            resource: 'webhook_event',
            eventName: 'refund.canceled',
            entityType: 'refund',
            entityId: 'refund_canceled_1',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(RefundCanceled::class, $event);
        $this->assertSame('refund_canceled_1', $event->refundId);
        $this->assertSame('canceled', $event->status);
    }

    public function test_refund_events_degrade_to_unsupported_without_a_get_refund_action(): void
    {
        // Back-compat: a factory built without GetRefund (the pre-refund shape)
        // must treat refund webhooks as unsupported, not fatal.
        $factory = new WebhookEventFactory($this->getOrder, $this->getSubscription);

        $webhook = new WebhookReceived(
            id: 'webhook_event_rf',
            resource: 'webhook_event',
            eventName: 'refund.completed',
            entityType: 'refund',
            entityId: 'refund_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = $factory->createFromWebhook($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $event);
        $this->assertFalse($factory->isSupported('refund.completed'));
        $this->assertNotContains('refund.completed', $factory->getSupportedEvents());
        // Non-refund events are unaffected.
        $this->assertTrue($factory->isSupported('order.paid'));
    }

    public function test_it_creates_checkout_paid_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_cp',
            resource: 'webhook_event',
            eventName: 'checkout.paid',
            entityType: 'checkout',
            entityId: 'checkout_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'orderId' => 'ord_789',
                'status' => 'paid',
                'metadata' => ['cart_id' => 'cart_1'],
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(CheckoutPaid::class, $event);
        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_789', $event->orderId);
        $this->assertSame('paid', $event->status);
        $this->assertSame(['cart_id' => 'cart_1'], $event->metadata);
    }

    public function test_it_creates_checkout_failed_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_cf',
            resource: 'webhook_event',
            eventName: 'checkout.failed',
            entityType: 'checkout',
            entityId: 'checkout_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456', 'status' => 'failed'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(CheckoutFailed::class, $event);
        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertNull($event->orderId);
        $this->assertSame('failed', $event->status);
    }

    public function test_it_creates_checkout_canceled_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_cc',
            resource: 'webhook_event',
            eventName: 'checkout.canceled',
            entityType: 'checkout',
            entityId: 'checkout_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['customerId' => 'cus_456'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(CheckoutCanceled::class, $event);
        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('canceled', $event->status);
    }

    public function test_it_creates_checkout_expired_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_ce',
            resource: 'webhook_event',
            eventName: 'checkout.expired',
            entityType: 'checkout',
            entityId: 'checkout_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(CheckoutExpired::class, $event);
        $this->assertSame('checkout_123', $event->checkoutId);
        $this->assertNull($event->customerId);
        $this->assertSame('expired', $event->status);
    }

    public function test_it_creates_subscription_cancellation_grace_period_completed_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_gpc',
            resource: 'webhook_event',
            eventName: 'subscription.cancellation_grace_period_completed',
            entityType: 'subscription',
            entityId: 'sub_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [
                'customerId' => 'cus_456',
                'endedAt' => '2024-02-15T10:00:00Z',
            ],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCancellationGracePeriodCompleted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endsAt);
        $this->assertSame('2024-02-15T10:00:00+00:00', $event->endsAt->format('c'));
    }

    public function test_it_creates_unsupported_webhook_received_for_unknown_events(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_abc',
            resource: 'webhook_event',
            eventName: 'unknown.event',
            entityType: 'unknown',
            entityId: 'res_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: [],
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(UnsupportedWebhookReceived::class, $event);
        $this->assertSame('unknown.event', $event->eventName);
    }

    public function test_it_creates_webhook_setup_received_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            id: 'webhook_event_setup',
            resource: 'webhook_event',
            eventName: 'webhook.setup',
            entityType: 'webhook',
            entityId: 'wh_123',
            testmode: false,
            createdAt: '2024-01-15T10:00:00Z',
            object: ['url' => 'https://example.test/webhooks/vatly'],
        );

        $event = $this->factory->createFromWebhook($webhook);

        // Regression: `webhook.setup` used to fall through to Unsupported.
        $this->assertInstanceOf(WebhookSetupReceived::class, $event);
        $this->assertSame('webhook.setup', $event->eventName);
        $this->assertSame('wh_123', $event->entityId);
        $this->assertSame(['url' => 'https://example.test/webhooks/vatly'], $event->object);
    }

    public function test_it_returns_list_of_supported_events(): void
    {
        $supported = $this->factory->getSupportedEvents();

        $this->assertContains('subscription.started', $supported);
        $this->assertContains('subscription.billing_updated', $supported);
        $this->assertContains('subscription.resumed', $supported);
        $this->assertContains('subscription.canceled_immediately', $supported);
        $this->assertContains('subscription.canceled_with_grace_period', $supported);
        $this->assertContains('subscription.cancellation_grace_period_completed', $supported);
        $this->assertContains('order.paid', $supported);
        $this->assertContains('order.canceled', $supported);
        $this->assertContains('order.chargeback_received', $supported);
        $this->assertContains('order.chargeback_reversed', $supported);
        $this->assertContains('order.payment_failed', $supported);
        $this->assertContains('checkout.paid', $supported);
        $this->assertContains('checkout.failed', $supported);
        $this->assertContains('checkout.canceled', $supported);
        $this->assertContains('checkout.expired', $supported);
        $this->assertContains('refund.completed', $supported);
        $this->assertContains('refund.failed', $supported);
        $this->assertContains('refund.canceled', $supported);
        $this->assertContains('webhook.setup', $supported);
    }

    public function test_it_checks_if_event_is_supported(): void
    {
        $this->assertTrue($this->factory->isSupported('subscription.started'));
        $this->assertTrue($this->factory->isSupported('subscription.billing_updated'));
        $this->assertTrue($this->factory->isSupported('subscription.resumed'));
        $this->assertTrue($this->factory->isSupported('order.paid'));
        $this->assertTrue($this->factory->isSupported('order.canceled'));
        $this->assertTrue($this->factory->isSupported('order.chargeback_received'));
        $this->assertTrue($this->factory->isSupported('order.chargeback_reversed'));
        $this->assertTrue($this->factory->isSupported('order.payment_failed'));
        $this->assertTrue($this->factory->isSupported('checkout.paid'));
        $this->assertTrue($this->factory->isSupported('checkout.failed'));
        $this->assertTrue($this->factory->isSupported('checkout.canceled'));
        $this->assertTrue($this->factory->isSupported('checkout.expired'));
        $this->assertTrue($this->factory->isSupported('subscription.cancellation_grace_period_completed'));
        $this->assertTrue($this->factory->isSupported('refund.completed'));
        $this->assertTrue($this->factory->isSupported('refund.failed'));
        $this->assertTrue($this->factory->isSupported('refund.canceled'));
        $this->assertTrue($this->factory->isSupported('webhook.setup'));
        $this->assertFalse($this->factory->isSupported('unknown.event'));
    }

    /**
     * @param array{
     *   id: string,
     *   customerId: string,
     *   total: array{currency: string, value: string},
     *   subtotal: array{currency: string, value: string},
     *   taxRates: array<int, array{name: string, percentage: float, taxablePercentage: float, amount: string}>,
     *   invoiceNumber: ?string,
     *   paymentMethod: ?string,
     *   status?: string,
     * Build a minimal API Subscription resource for enrichment-path tests.
     *
     * @param array{
     *     id: string,
     *     customerId: string,
     *     subscriptionPlanId: string,
     *     name: string,
     *     quantity: int,
     *     mandate: ?Mandate,
     * } $data
     */
    private function makeApiSubscription(array $data): ApiSubscription
    {
        $subscription = new ApiSubscription(Mockery::mock(VatlyApiClient::class));
        $subscription->id = $data['id'];
        $subscription->customerId = $data['customerId'];
        $subscription->subscriptionPlanId = $data['subscriptionPlanId'];
        $subscription->name = $data['name'];
        $subscription->quantity = $data['quantity'];
        $subscription->mandate = $data['mandate'];

        return $subscription;
    }

    /**
     * } $data
     */
    private function buildApiOrder(array $data): ApiOrder
    {
        $order = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $order->id = $data['id'];
        $order->customerId = $data['customerId'];
        $order->total = new Money($data['total']['currency'], $data['total']['value']);
        $order->subtotal = new Money($data['subtotal']['currency'], $data['subtotal']['value']);
        $order->invoiceNumber = $data['invoiceNumber'];
        $order->paymentMethod = $data['paymentMethod'];
        $order->status = $data['status'] ?? 'paid';

        $taxItems = array_map(
            fn (array $rate) => [
                'taxRate' => [
                    'name' => $rate['name'],
                    'percentage' => $rate['percentage'],
                    'taxablePercentage' => $rate['taxablePercentage'],
                ],
                'amount' => ['currency' => $data['total']['currency'], 'value' => $rate['amount']],
            ],
            $data['taxRates'],
        );
        $order->taxSummary = new TaxSummaryCollection($taxItems);

        $order->lines = array_map(
            fn (array $line) => (object) [
                'id' => $line['id'],
                'resource' => 'orderline',
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'productType' => $line['productType'] ?? null,
                'productId' => $line['productId'] ?? null,
                'basePrice' => (object) ['currency' => $data['total']['currency'], 'value' => $line['basePrice']],
                'total' => (object) ['currency' => $data['total']['currency'], 'value' => $line['total']],
                'subtotal' => (object) ['currency' => $data['total']['currency'], 'value' => $line['subtotal']],
                'taxes' => [],
            ],
            $data['lines'] ?? [],
        );

        return $order;
    }

    /**
     * Build a minimal API Refund resource for enrichment-path tests.
     *
     * @param array{
     *   id: string,
     *   customerId: string,
     *   status: string,
     *   originalOrderId: string,
     *   total: array{currency: string, value: string},
     *   subtotal: array{currency: string, value: string},
     *   taxRates: array<int, array{name: string, percentage: float, taxablePercentage: float, amount: string}>,
     * } $data
     */
    private function buildApiRefund(array $data): ApiRefund
    {
        $refund = new ApiRefund(Mockery::mock(VatlyApiClient::class));
        $refund->id = $data['id'];
        $refund->customerId = $data['customerId'];
        $refund->status = $data['status'];
        $refund->originalOrderId = $data['originalOrderId'];
        $refund->total = new Money($data['total']['currency'], $data['total']['value']);
        $refund->subtotal = new Money($data['subtotal']['currency'], $data['subtotal']['value']);

        $taxItems = array_map(
            fn (array $rate) => [
                'taxRate' => [
                    'name' => $rate['name'],
                    'percentage' => $rate['percentage'],
                    'taxablePercentage' => $rate['taxablePercentage'],
                ],
                'amount' => ['currency' => $data['total']['currency'], 'value' => $rate['amount']],
            ],
            $data['taxRates'],
        );
        $refund->taxSummary = new TaxSummaryCollection($taxItems);

        return $refund;
    }
}
