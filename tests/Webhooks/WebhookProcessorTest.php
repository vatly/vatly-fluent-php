<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use Mockery;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\API\Webhooks\Events\CheckoutPaid;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;
use Vatly\API\Webhooks\Events\SubscriptionStarted;
use Vatly\API\Webhooks\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Webhooks\WebhookProcessor;

/**
 * End-to-end: the processor verifies the HMAC signature, builds a typed event
 * straight from the signed (fat) webhook payload — no follow-up API GET — runs
 * matching reactions, then dispatches. The {@see WebhookEventFactory} holds a
 * real, un-stubbed {@see VatlyApiClient} used only for in-memory hydration; any
 * HTTP attempt would fail (no API key), proving "zero API calls" by construction.
 */
class WebhookProcessorTest extends TestCase
{
    private string $secret;
    private WebhookEventFactory $eventFactory;
    private WebhookCallRepositoryInterface $repository;
    private EventDispatcherInterface $dispatcher;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = 'test-webhook-secret';
        $this->eventFactory = new WebhookEventFactory(new VatlyApiClient());
        $this->repository = Mockery::mock(WebhookCallRepositoryInterface::class);
        $this->dispatcher = Mockery::mock(EventDispatcherInterface::class);

        $this->processor = new WebhookProcessor(
            $this->eventFactory,
            $this->repository,
            $this->dispatcher,
            $this->secret,
        );
    }

    public function test_it_processes_a_valid_webhook_end_to_end(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_abc',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: $this->fatSubscription([
                'id' => 'sub_123',
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
                'mandate' => null,
            ]),
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->repository
            ->shouldReceive('record')
            ->once()
            ->withArgs(function (
                string $id,
                string $resource,
                string $eventName,
                string $entityType,
                string $entityId,
                bool $testmode,
                \DateTimeInterface $createdAt,
                array $object,
                ?string $vatlyCustomerId,
            ) {
                return $id === 'webhook_event_abc'
                    && $resource === 'webhook_event'
                    && $eventName === 'subscription.started'
                    && $entityType === 'subscription'
                    && $entityId === 'sub_123'
                    && $testmode === false
                    && $createdAt->format('Y-m-d') === '2024-01-15'
                    && $object['customerId'] === 'cus_456'
                    && $vatlyCustomerId === 'cus_456';
            });

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event) {
                return $event instanceof SubscriptionStarted
                    && $event->customerId === 'cus_456'
                    && $event->subscriptionId === 'sub_123'
                    && $event->planId === 'plan_789';
            });

        $this->processor->handle($payload, $signature);
    }

    public function test_it_runs_matching_reactions_before_dispatching(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_xyz',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
            object: $this->fatSubscription([
                'id' => 'sub_123',
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
                'mandate' => null,
            ]),
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->repository->shouldReceive('record')->once();
        $this->dispatcher->shouldReceive('dispatch')->once();

        $matchingReaction = Mockery::mock(WebhookReactionInterface::class);
        $matchingReaction->shouldReceive('supports')->once()->andReturn(true);
        $matchingReaction->shouldReceive('handle')->once()->withArgs(function ($event) {
            return $event instanceof SubscriptionStarted;
        });

        $nonMatchingReaction = Mockery::mock(WebhookReactionInterface::class);
        $nonMatchingReaction->shouldReceive('supports')->once()->andReturn(false);
        $nonMatchingReaction->shouldNotReceive('handle');

        $processor = new WebhookProcessor(
            $this->eventFactory,
            $this->repository,
            $this->dispatcher,
            $this->secret,
            reactions: [$matchingReaction, $nonMatchingReaction],
        );

        $processor->handle($payload, $signature);
    }

    public function test_it_processes_a_payment_failed_webhook_end_to_end(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_pf',
            eventName: 'order.payment_failed',
            entityType: 'order',
            entityId: 'ord_dunning_1',
            object: $this->fatOrder([
                'id' => 'ord_dunning_1',
                'customerId' => 'cus_456',
                'status' => 'pending',
                'total' => ['currency' => 'EUR', 'value' => '49.00'],
                'subtotal' => ['currency' => 'EUR', 'value' => '40.50'],
                'paymentMethod' => 'sepa_direct_debit',
                'taxRates' => [
                    ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '8.50'],
                ],
            ]),
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->repository->shouldReceive('record')->once();

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event) {
                return $event instanceof OrderPaymentFailed
                    && $event->customerId === 'cus_456'
                    && $event->orderId === 'ord_dunning_1'
                    && $event->status === 'pending'
                    && $event->total->toCents() === 4900
                    && $event->subtotal->toCents() === 4050
                    && $event->total->currency === 'EUR'
                    && $event->paymentMethod === 'sepa_direct_debit'
                    && $event->taxSummary->items[0]->amount->toCents() === 850;
            });

        $this->processor->handle($payload, $signature);
    }

    public function test_it_processes_a_checkout_paid_webhook_end_to_end(): void
    {
        // Checkout events route straight from the payload envelope (no money/tax
        // resource to hydrate), like every other event — no API GET ever.
        $payload = $this->makePayload(
            id: 'webhook_event_cp',
            eventName: 'checkout.paid',
            entityType: 'checkout',
            entityId: 'checkout_123',
            object: [
                'customerId' => 'cus_456',
                'orderId' => 'ord_789',
                'status' => 'paid',
                'metadata' => ['cart_id' => 'cart_1'],
            ],
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->repository
            ->shouldReceive('record')
            ->once()
            ->withArgs(function (
                string $id,
                string $resource,
                string $eventName,
                string $entityType,
                string $entityId,
                bool $testmode,
                \DateTimeInterface $createdAt,
                array $object,
                ?string $vatlyCustomerId,
            ) {
                return $id === 'webhook_event_cp'
                    && $eventName === 'checkout.paid'
                    && $entityType === 'checkout'
                    && $entityId === 'checkout_123'
                    && $vatlyCustomerId === 'cus_456';
            });

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event) {
                return $event instanceof CheckoutPaid
                    && $event->checkoutId === 'checkout_123'
                    && $event->customerId === 'cus_456'
                    && $event->orderId === 'ord_789'
                    && $event->status === 'paid'
                    && $event->metadata === ['cart_id' => 'cart_1'];
            });

        $this->processor->handle($payload, $signature);
    }

    public function test_it_throws_exception_for_invalid_signature(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_abc',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
        );

        $this->repository->shouldNotReceive('record');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(InvalidWebhookSignatureException::class);

        $this->processor->handle($payload, 't='.time().',v1=deadbeef');
    }

    public function test_it_throws_exception_for_missing_signature(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_abc',
            eventName: 'subscription.started',
            entityType: 'subscription',
            entityId: 'sub_123',
        );

        $this->repository->shouldNotReceive('record');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(InvalidWebhookSignatureException::class);

        $this->processor->handle($payload, '');
    }

    public function test_it_throws_exception_for_malformed_payload(): void
    {
        $this->repository->shouldNotReceive('record');
        $this->dispatcher->shouldNotReceive('dispatch');

        $payload = json_encode(['eventName' => 'subscription.started']); // missing required fields
        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->expectException(InvalidWebhookSignatureException::class);

        $this->processor->handle($payload, $signature);
    }

    public function test_it_dispatches_unsupported_webhook_received_for_unknown_events(): void
    {
        $payload = $this->makePayload(
            id: 'webhook_event_abc',
            eventName: 'unknown.event',
            entityType: 'unknown',
            entityId: 'res_123',
            object: [],
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $this->repository->shouldReceive('record')->once();

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event) {
                return $event instanceof UnsupportedWebhookReceived
                    && $event->eventName === 'unknown.event';
            });

        $this->processor->handle($payload, $signature);
    }

    /**
     * @param array<string, mixed> $object
     */
    private function makePayload(
        string $id,
        string $eventName,
        string $entityType,
        string $entityId,
        array $object = [],
        string $resource = 'webhook_event',
        bool $testmode = false,
        string $createdAt = '2024-01-15T10:00:00Z',
    ): string {
        return (string) json_encode([
            'id' => $id,
            'resource' => $resource,
            'eventName' => $eventName,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'testmode' => $testmode,
            'createdAt' => $createdAt,
            'object' => (object) $object,
        ]);
    }

    private function makeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * The fat (signed) `subscription` webhook `object`.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function fatSubscription(array $data): array
    {
        return [
            'id' => $data['id'],
            'resource' => 'subscription',
            'customerId' => $data['customerId'],
            'subscriptionPlanId' => $data['subscriptionPlanId'],
            'testmode' => false,
            'name' => $data['name'],
            'description' => 'A subscription',
            'quantity' => $data['quantity'],
            'interval' => '1 month',
            'intervalCount' => 1,
            'status' => 'active',
            'startedAt' => '2024-01-15T10:00:00+00:00',
            'endedAt' => null,
            'canceledAt' => null,
            'renewedAt' => null,
            'renewedUntil' => null,
            'nextRenewalAt' => null,
            'mandate' => $data['mandate'],
        ];
    }

    /**
     * The fat (signed) `order` webhook `object`.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function fatOrder(array $data): array
    {
        $currency = $data['total']['currency'];

        return [
            'id' => $data['id'],
            'resource' => 'order',
            'customerId' => $data['customerId'],
            'createdAt' => '2024-01-15T10:00:00+00:00',
            'testmode' => false,
            'status' => $data['status'],
            'total' => $data['total'],
            'subtotal' => $data['subtotal'],
            'reversedSubtotal' => ['currency' => $currency, 'value' => '0.00'],
            'refundableSubtotal' => $data['subtotal'],
            'invoiceNumber' => $data['invoiceNumber'] ?? null,
            'paymentMethod' => $data['paymentMethod'] ?? null,
            'taxSummary' => array_map(
                fn (array $rate) => [
                    'taxRate' => [
                        'name' => $rate['name'],
                        'percentage' => $rate['percentage'],
                        'taxablePercentage' => $rate['taxablePercentage'],
                    ],
                    'amount' => ['currency' => $currency, 'value' => $rate['amount']],
                ],
                $data['taxRates'],
            ),
            'metadata' => null,
            'lines' => [],
        ];
    }
}
