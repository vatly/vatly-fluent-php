<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Events\PaymentFailed;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Webhooks\WebhookProcessor;

class WebhookProcessorTest extends TestCase
{
    private string $secret;
    private GetOrder $getOrder;
    private WebhookEventFactory $eventFactory;
    private WebhookCallRepositoryInterface $repository;
    private EventDispatcherInterface $dispatcher;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = 'test-webhook-secret';
        $this->getOrder = Mockery::mock(GetOrder::class);
        $this->eventFactory = new WebhookEventFactory($this->getOrder);
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
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
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
            object: [
                'customerId' => 'cus_456',
                'subscriptionPlanId' => 'plan_789',
                'name' => 'Premium Plan',
                'quantity' => 1,
            ],
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
            eventName: 'payment.failed',
            entityType: 'order',
            entityId: 'ord_dunning_1',
            object: [
                'customerId' => 'cus_456',
                'total' => ['currency' => 'EUR', 'value' => '49.00'],
            ],
        );

        $signature = $this->makeSignatureHeader($payload, $this->secret);

        $apiOrder = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $apiOrder->id = 'ord_dunning_1';
        $apiOrder->customerId = 'cus_456';
        $apiOrder->total = new Money('EUR', '49.00');
        $apiOrder->subtotal = new Money('EUR', '40.50');
        $apiOrder->invoiceNumber = null;
        $apiOrder->paymentMethod = 'sepa_direct_debit';
        $apiOrder->status = 'pending';
        $apiOrder->taxSummary = new TaxSummaryCollection([
            [
                'taxRate' => ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0],
                'amount' => ['currency' => 'EUR', 'value' => '8.50'],
            ],
        ]);

        $this->getOrder->shouldReceive('execute')
            ->once()
            ->with('ord_dunning_1')
            ->andReturn($apiOrder);

        $this->repository->shouldReceive('record')->once();

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event) {
                return $event instanceof PaymentFailed
                    && $event->customerId === 'cus_456'
                    && $event->orderId === 'ord_dunning_1'
                    && $event->status === 'pending'
                    && $event->total === 4900
                    && $event->subtotal === 4050
                    && $event->currency === 'EUR'
                    && $event->paymentMethod === 'sepa_direct_debit'
                    && $event->taxSummary->items[0]->amount === 850;
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
}
