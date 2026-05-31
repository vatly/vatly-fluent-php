<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Webhooks;

use Mockery;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Tests\TestCase;
use Vatly\Fluent\Webhooks\Reactions\CancelSubscriptionOnCanceled;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaid;
use Vatly\Fluent\Webhooks\Reactions\StoreOrderOnPaymentFailed;
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
            getOrder: Mockery::mock(GetOrder::class),
            getSubscription: Mockery::mock(GetSubscription::class),
        );

        $this->assertInstanceOf(WebhookProcessor::class, $processor);

        $reactions = $processor->getReactions();

        $this->assertCount(4, $reactions);
        $this->assertInstanceOf(SyncSubscriptionOnStarted::class, $reactions[0]);
        $this->assertInstanceOf(CancelSubscriptionOnCanceled::class, $reactions[1]);
        $this->assertInstanceOf(StoreOrderOnPaid::class, $reactions[2]);
        $this->assertInstanceOf(StoreOrderOnPaymentFailed::class, $reactions[3]);
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
            getOrder: Mockery::mock(GetOrder::class),
            getSubscription: Mockery::mock(GetSubscription::class),
            additionalReactions: [$custom],
        );

        $reactions = $processor->getReactions();

        $this->assertCount(5, $reactions);
        $this->assertSame($custom, $reactions[4]);
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
            getOrder: Mockery::mock(GetOrder::class),
            getSubscription: Mockery::mock(GetSubscription::class),
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
