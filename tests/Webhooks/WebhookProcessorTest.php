<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use DateTimeInterface;
use Mockery;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Events\SubscriptionStarted;
use Vatly\Fluent\Events\UnsupportedWebhookReceived;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Webhooks\WebhookProcessor;

class WebhookProcessorTest extends TestCase
{
    private string $secret;
    private SignatureVerifier $signatureVerifier;
    private WebhookEventFactory $eventFactory;
    private WebhookCallRepositoryInterface $repository;
    private EventDispatcherInterface $dispatcher;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = 'test-webhook-secret';
        $this->signatureVerifier = new SignatureVerifier();
        $this->eventFactory = new WebhookEventFactory();
        $this->repository = Mockery::mock(WebhookCallRepositoryInterface::class);
        $this->dispatcher = Mockery::mock(EventDispatcherInterface::class);

        $this->processor = new WebhookProcessor(
            $this->signatureVerifier,
            $this->eventFactory,
            $this->repository,
            $this->dispatcher,
            $this->secret,
        );
    }

    public function test_it_processes_a_valid_webhook_end_to_end(): void
    {
        $payload = json_encode([
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Premium Plan',
                    'quantity' => 1,
                ],
            ],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => false,
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

        $this->repository
            ->shouldReceive('record')
            ->once()
            ->withArgs(function (
                string $eventName,
                string $resourceId,
                string $resourceName,
                array $recordedPayload,
                DateTimeInterface $raisedAt,
                bool $testmode,
                ?string $vatlyCustomerId,
            ) {
                return $eventName === 'subscription.started'
                    && $resourceId === 'sub_123'
                    && $resourceName === 'subscription'
                    && $recordedPayload['eventName'] === 'subscription.started'
                    && $raisedAt->format('Y-m-d') === '2024-01-15'
                    && $testmode === false
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
        $payload = json_encode([
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Premium Plan',
                    'quantity' => 1,
                ],
            ],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => false,
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

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
            $this->signatureVerifier,
            $this->eventFactory,
            $this->repository,
            $this->dispatcher,
            $this->secret,
            reactions: [$matchingReaction, $nonMatchingReaction],
        );

        $processor->handle($payload, $signature);
    }

    public function test_it_throws_exception_for_invalid_signature(): void
    {
        $payload = json_encode([
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => [],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => false,
        ]);

        $this->repository->shouldNotReceive('record');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(InvalidWebhookSignatureException::class);

        $this->processor->handle($payload, 'invalid-signature');
    }

    public function test_it_throws_exception_for_missing_signature(): void
    {
        $payload = json_encode([
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => [],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => false,
        ]);

        $this->repository->shouldNotReceive('record');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->expectException(InvalidWebhookSignatureException::class);

        $this->processor->handle($payload, '');
    }

    public function test_it_dispatches_unsupported_webhook_received_for_unknown_events(): void
    {
        $payload = json_encode([
            'eventName' => 'unknown.event',
            'resourceId' => 'res_123',
            'resourceName' => 'unknown',
            'object' => [],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => false,
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

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

    public function test_it_records_webhook_with_testmode_flag(): void
    {
        $payload = json_encode([
            'eventName' => 'subscription.started',
            'resourceId' => 'sub_123',
            'resourceName' => 'subscription',
            'object' => [
                'data' => [
                    'customerId' => 'cus_456',
                    'subscriptionPlanId' => 'plan_789',
                    'name' => 'Test Plan',
                    'quantity' => 1,
                ],
            ],
            'raisedAt' => '2024-01-15T10:00:00Z',
            'testmode' => true,
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

        $this->repository
            ->shouldReceive('record')
            ->once()
            ->withArgs(function (
                string $eventName,
                string $resourceId,
                string $resourceName,
                array $recordedPayload,
                DateTimeInterface $raisedAt,
                bool $testmode,
                ?string $vatlyCustomerId,
            ) {
                return $testmode === true;
            });

        $this->dispatcher->shouldReceive('dispatch')->once();

        $this->processor->handle($payload, $signature);
    }
}
