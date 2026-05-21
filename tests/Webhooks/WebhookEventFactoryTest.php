<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use DateTimeInterface;
use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Events\OrderPaid;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->getOrder = Mockery::mock(GetOrder::class);
        $this->factory = new WebhookEventFactory($this->getOrder);
    }

    public function test_it_parses_webhook_payload_into_webhook_received_event(): void
    {
        $payload = [
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => ['data' => ['customerId' => 'cus_456']],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => true,
        ];

        $event = $this->factory->parsePayload($payload);

        $this->assertInstanceOf(WebhookReceived::class, $event);
        $this->assertSame('subscription.started', $event->eventName);
        $this->assertSame('sub_123', $event->resourceId);
        $this->assertSame('subscription', $event->resourceName);
        $this->assertTrue($event->testmode);
        $this->assertSame('cus_456', $event->getCustomerId());
    }

    public function test_it_creates_subscription_started_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.started',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Premium Plan',
                    'quantity' => 1,
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionStarted::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
        $this->assertSame('plan_789', $event->planId);
        $this->assertSame('Premium Plan', $event->name);
        $this->assertSame(1, $event->quantity);
    }

    public function test_it_creates_subscription_canceled_immediately_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.canceled_immediately',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(SubscriptionCanceledImmediately::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('sub_123', $event->subscriptionId);
    }

    public function test_it_creates_subscription_canceled_with_grace_period_event_from_webhook(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'subscription.canceled_with_grace_period',
            resourceId: 'sub_123',
            resourceName: 'subscription',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'endsAt' => '2024-02-15T10:00:00Z',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
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
            eventName: 'order.paid',
            resourceId: 'ord_123',
            resourceName: 'order',
            object: [
                'data' => [
                    'customerId' => 'cus_456',
                    'total' => 9900,
                    'currency' => 'EUR',
                    'invoiceNumber' => 'INV-2024-001',
                    'paymentMethod' => 'credit_card',
                ],
            ],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
        );

        $event = $this->factory->createFromWebhook($webhook);

        $this->assertInstanceOf(OrderPaid::class, $event);
        $this->assertSame('cus_456', $event->customerId);
        $this->assertSame('ord_123', $event->orderId);
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

    public function test_it_creates_unsupported_webhook_received_for_unknown_events(): void
    {
        $webhook = new WebhookReceived(
            eventName: 'unknown.event',
            resourceId: 'res_123',
            resourceName: 'unknown',
            object: [],
            raisedAt: '2024-01-15T10:00:00Z',
            testmode: false,
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
    }

    public function test_it_checks_if_event_is_supported(): void
    {
        $this->assertTrue($this->factory->isSupported('subscription.started'));
        $this->assertTrue($this->factory->isSupported('order.paid'));
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
        $order->status = 'paid';

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
