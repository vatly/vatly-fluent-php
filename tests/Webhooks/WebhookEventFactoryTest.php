<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use DateTimeInterface;
use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\API\Webhooks\WebhookPayload;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Events\OrderPaid;
use Vatly\Fluent\Events\PaymentFailed;
use Vatly\Fluent\Events\SubscriptionCanceledImmediately;
use Vatly\Fluent\Events\SubscriptionCanceledWithGracePeriod;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\WebhookEventFactory;

class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;
    private GetOrder $getOrder;
    private GetSubscription $getSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getOrder = Mockery::mock(GetOrder::class);
        $this->getSubscription = Mockery::mock(GetSubscription::class);
        $this->factory = new WebhookEventFactory($this->getOrder, $this->getSubscription);
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
        // Fallback path can't enrich mandate from the webhook payload.
        $this->assertNull($event->mandate);
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
        $this->assertSame(9900, $event->total);
        $this->assertSame(8182, $event->subtotal);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('INV-2024-001', $event->invoiceNumber);
        $this->assertSame('credit_card', $event->paymentMethod);
        $this->assertCount(1, $event->taxSummary);
        $this->assertSame('VAT', $event->taxSummary->items[0]->rate->name);
        $this->assertSame(21.0, $event->taxSummary->items[0]->rate->percentage);
        $this->assertSame(1718, $event->taxSummary->items[0]->amount);
        $this->assertSame('EUR', $event->taxSummary->items[0]->currency);
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
            eventName: 'payment.failed',
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

        $this->assertInstanceOf(PaymentFailed::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_dunning_1', $event->orderId);
        $this->assertSame('pending', $event->status);
        $this->assertSame(4900, $event->total);
        $this->assertSame(4050, $event->subtotal);
        $this->assertSame('EUR', $event->currency);
        $this->assertNull($event->invoiceNumber);
        $this->assertSame('sepa_direct_debit', $event->paymentMethod);
        $this->assertCount(1, $event->taxSummary);
        $this->assertSame('VAT', $event->taxSummary->items[0]->rate->name);
        $this->assertSame(850, $event->taxSummary->items[0]->amount);
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

    public function test_it_returns_list_of_supported_events(): void
    {
        $supported = $this->factory->getSupportedEvents();

        $this->assertContains('subscription.started', $supported);
        $this->assertContains('subscription.canceled_immediately', $supported);
        $this->assertContains('subscription.canceled_with_grace_period', $supported);
        $this->assertContains('order.paid', $supported);
        $this->assertContains('payment.failed', $supported);
    }

    public function test_it_checks_if_event_is_supported(): void
    {
        $this->assertTrue($this->factory->isSupported('subscription.started'));
        $this->assertTrue($this->factory->isSupported('order.paid'));
        $this->assertTrue($this->factory->isSupported('payment.failed'));
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

        return $order;
    }
}
