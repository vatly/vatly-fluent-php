<?php

namespace Vatly\API\Endpoints;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\SubscriptionPlan;
use Vatly\API\Resources\SubscriptionPlanCollection;

class SubscriptionPlanEndpoint extends BaseEndpoint
{
    protected string $resourcePath = "subscription-plans";

    const RESOURCE_ID_PREFIX = 'subscription_plan_';

    protected function getResourceObject(): SubscriptionPlan
    {
        return new SubscriptionPlan($this->client);
    }


    /**
     * @throws ApiException
     * @return SubscriptionPlan|BaseResource
     */
    public function get(string $id, array $parameters = []): BaseResource
    {
        return $this->rest_read($id, $parameters);
    }

    /**
     * @return SubscriptionPlanCollection|BaseResourcePage
     * @throws ApiException
     */
    public function page(
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $parameters = []
    ): BaseResourcePage {
        return $this->rest_list($startingAfter, $endingBefore, $limit, $parameters);
    }

    protected function getResourcePageObject(int $count, PaginationLinks $links): BaseResourcePage
    {
        return new SubscriptionPlanCollection($this->client, $count, $links);
    }
}
