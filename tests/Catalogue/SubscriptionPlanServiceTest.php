<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Catalogue;

use Mockery;
use Vatly\API\Endpoints\SubscriptionPlanEndpoint;
use Vatly\API\Resources\SubscriptionPlan;
use Vatly\API\Resources\SubscriptionPlanCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Catalogue\SubscriptionPlanService;
use Vatly\Fluent\Tests\TestCase;

class SubscriptionPlanServiceTest extends TestCase
{
    private VatlyApiClient $apiClient;
    private SubscriptionPlanEndpoint $endpoint;
    private SubscriptionPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiClient = Mockery::mock(VatlyApiClient::class);
        $this->endpoint = Mockery::mock(SubscriptionPlanEndpoint::class);
        $this->apiClient->subscriptionPlans = $this->endpoint;
        $this->service = new SubscriptionPlanService($this->apiClient);
    }

    public function test_create_forwards_to_the_endpoint(): void
    {
        $plan = $this->makePlan('subscription_plan_1');

        $this->endpoint->shouldReceive('create')
            ->once()
            ->with(['name' => 'Pro', 'interval' => 'month', 'productType' => 'saas'], [])
            ->andReturn($plan);

        $result = $this->service->create([
            'name' => 'Pro',
            'interval' => 'month',
            'productType' => 'saas',
        ]);

        $this->assertSame($plan, $result);
    }

    public function test_find_forwards_to_the_endpoint(): void
    {
        $plan = $this->makePlan('subscription_plan_1');

        $this->endpoint->shouldReceive('get')
            ->once()
            ->with('subscription_plan_1', [])
            ->andReturn($plan);

        $this->assertSame($plan, $this->service->find('subscription_plan_1'));
    }

    public function test_update_forwards_to_the_endpoint(): void
    {
        $plan = $this->makePlan('subscription_plan_1');
        $plan->updateStatus = 'pending';

        $this->endpoint->shouldReceive('update')
            ->once()
            ->with('subscription_plan_1', ['basePrice' => ['value' => '19.00', 'currency' => 'EUR']], [])
            ->andReturn($plan);

        $result = $this->service->update('subscription_plan_1', [
            'basePrice' => ['value' => '19.00', 'currency' => 'EUR'],
        ]);

        $this->assertSame($plan, $result);
        $this->assertSame('pending', $result->updateStatus);
    }

    public function test_update_can_return_null(): void
    {
        $this->endpoint->shouldReceive('update')->once()->andReturnNull();

        $this->assertNull($this->service->update('subscription_plan_1', ['name' => 'x']));
    }

    public function test_archive_forwards_to_the_endpoint(): void
    {
        $this->endpoint->shouldReceive('archive')
            ->once()
            ->with('subscription_plan_1', []);

        $this->service->archive('subscription_plan_1');
    }

    public function test_unarchive_forwards_to_the_endpoint(): void
    {
        $plan = $this->makePlan('subscription_plan_1');

        $this->endpoint->shouldReceive('unarchive')
            ->once()
            ->with('subscription_plan_1', [])
            ->andReturn($plan);

        $this->assertSame($plan, $this->service->unarchive('subscription_plan_1'));
    }

    public function test_list_forwards_pagination_and_filters(): void
    {
        $collection = Mockery::mock(SubscriptionPlanCollection::class);

        $this->endpoint->shouldReceive('page')
            ->once()
            ->with(null, null, null, ['includeArchived' => true])
            ->andReturn($collection);

        $result = $this->service->list(parameters: ['includeArchived' => true]);

        $this->assertSame($collection, $result);
    }

    private function makePlan(string $id): SubscriptionPlan
    {
        $plan = new SubscriptionPlan($this->apiClient);
        $plan->id = $id;

        return $plan;
    }
}
