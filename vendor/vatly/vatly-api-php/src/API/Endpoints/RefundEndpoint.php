<?php

namespace Vatly\API\Endpoints;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\Refund;
use Vatly\API\Resources\RefundCollection;

class RefundEndpoint extends BaseEndpoint
{
    protected string $resourcePath = "refunds";

    const RESOURCE_ID_PREFIX = 'refund_';

    protected function getResourceObject(): Refund
    {
        return new Refund($this->client);
    }


    /**
     * @throws ApiException
     * @return Refund|BaseResource
     */
    public function get(string $id, array $parameters = [])
    {
        return $this->rest_read($id, $parameters);
    }

    /**
     * @return RefundCollection|BaseResourcePage
     * @throws ApiException
     */
    public function page(
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $parameters = []
    ) {
        return $this->rest_list($startingAfter, $endingBefore, $limit, $parameters);
    }

    protected function getResourcePageObject(int $count, PaginationLinks $links): BaseResourcePage
    {
        return new RefundCollection($this->client, $count, $links);
    }
}
