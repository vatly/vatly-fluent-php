<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use Mockery;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Contracts\ChargebackRepositoryInterface;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\RefundRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\CancelOrderOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\EndSubscriptionOnGracePeriodCompleted;
use Vatly\Fluent\Webhooks\Reactions\ResumeSubscriptionOnResumed;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaymentFailed;
use Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange;
use Vatly\Fluent\Webhooks\Reactions\SyncRefundOnStatusChange;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnBillingUpdated;
use Vatly\Fluent\Webhooks\Reactions\SyncSubscriptionOnStarted;
use Vatly\Fluent\Webhooks\WebhookProcessor;
use Vatly\Fluent\Webhooks\WebhookProcessorFactory;

class WebhookProcessorFactoryTest extends TestCase
{
    public function test_it_builds_a_processor_with_the_standard_reactions(): void
    {
        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
        );

        $this->assertInstanceOf(WebhookProcessor::class, $processor);

        $reactions = $processor->getReactions();

        $this->assertCount(8, $reactions);
        $this->assertInstanceOf(SyncSubscriptionOnStarted::class, $reactions[0]);
        $this->assertInstanceOf(SyncSubscriptionOnBillingUpdated::class, $reactions[1]);
        $this->assertInstanceOf(ResumeSubscriptionOnResumed::class, $reactions[2]);
        $this->assertInstanceOf(CancelSubscriptionOnCanceled::class, $reactions[3]);
        $this->assertInstanceOf(EndSubscriptionOnGracePeriodCompleted::class, $reactions[4]);
        $this->assertInstanceOf(StoreOrderOnPaid::class, $reactions[5]);
        $this->assertInstanceOf(StoreOrderOnPaymentFailed::class, $reactions[6]);
        $this->assertInstanceOf(CancelOrderOnCanceled::class, $reactions[7]);
    }

    public function test_it_builds_a_processor_without_optional_repositories(): void
    {
        // A driver that only wires subscriptions/orders passes neither `refunds`
        // nor `chargebacks` — this must not throw and must register no opt-in
        // persistence reaction.
        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
        );

        $reactions = $processor->getReactions();

        $this->assertInstanceOf(WebhookProcessor::class, $processor);
        $this->assertCount(8, $reactions);
        foreach ($reactions as $reaction) {
            $this->assertNotInstanceOf(SyncRefundOnStatusChange::class, $reaction);
            $this->assertNotInstanceOf(SyncChargebackOnStatusChange::class, $reaction);
        }
    }

    public function test_it_registers_the_refund_reaction_only_when_a_refund_repository_is_supplied(): void
    {
        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
            refunds: Mockery::mock(RefundRepositoryInterface::class),
        );

        $reactions = $processor->getReactions();

        $this->assertCount(9, $reactions);
        $this->assertInstanceOf(SyncRefundOnStatusChange::class, $reactions[8]);
    }

    public function test_it_registers_the_chargeback_reactions_only_when_a_chargeback_repository_is_supplied(): void
    {
        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
            chargebacks: Mockery::mock(ChargebackRepositoryInterface::class),
        );

        $reactions = $processor->getReactions();

        // 8 standard + 1 chargeback persistence reaction (no refunds wired here).
        $this->assertCount(9, $reactions);
        $this->assertInstanceOf(SyncChargebackOnStatusChange::class, $reactions[8]);
    }

    public function test_it_does_not_register_chargeback_reactions_without_a_repository(): void
    {
        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
        );

        foreach ($processor->getReactions() as $reaction) {
            $this->assertNotInstanceOf(SyncChargebackOnStatusChange::class, $reaction);
        }
    }

    public function test_it_appends_additional_reactions_after_the_standard_ones(): void
    {
        $custom = Mockery::mock(WebhookReactionInterface::class);

        $processor = WebhookProcessorFactory::create(
            config: $this->config('secret'),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
            additionalReactions: [$custom],
        );

        $reactions = $processor->getReactions();

        $this->assertCount(9, $reactions);
        $this->assertSame($custom, $reactions[8]);
    }

    public function test_it_tolerates_a_null_webhook_secret(): void
    {
        $processor = WebhookProcessorFactory::create(
            config: $this->config(null),
            subscriptions: Mockery::mock(SubscriptionRepositoryInterface::class),
            orders: Mockery::mock(OrderRepositoryInterface::class),
            webhookCalls: Mockery::mock(WebhookCallRepositoryInterface::class),
            dispatcher: Mockery::mock(EventDispatcherInterface::class),
            bindings: Mockery::mock(CustomerBindingRepository::class),
            apiClient: new VatlyApiClient(),
        );

        $this->assertInstanceOf(WebhookProcessor::class, $processor);
    }

    private function config(?string $secret): ConfigurationInterface
    {
        $config = Mockery::mock(ConfigurationInterface::class);
        $config->shouldReceive('getWebhookSecret')->andReturn($secret);

        return $config;
    }
}
