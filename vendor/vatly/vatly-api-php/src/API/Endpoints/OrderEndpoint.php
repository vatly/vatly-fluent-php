<?php

namespace Vatly\API\Endpoints;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\Order;
use Vatly\API\Resources\OrderCollection;
use Vatly\API\Types\Link;

class OrderEndpoint extends BaseEndpoint
{
    protected string $resourcePath = "orders";

    const RESOURCE_ID_PREFIX = 'order_';

    protected function getResourceObject(): Order
    {
        return new Order($this->client);
    }


    /**
     * @throws ApiException
     * @return Order|BaseResource
     */
    public function get(string $id, array $parameters = [])
    {
        $this->validateOrderId($id);

        return $this->rest_read($id, $parameters);
    }

    /**
     * @return OrderCollection|BaseResourcePage
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

    public function requestAddressUpdateLink(string $id, array $data = []): Link
    {
        $this->validateOrderId($id);

        $resource = "{$this->getResourcePath()}/" . urlencode($id) . "/request-address-update-link";

        $body = null;
        if (count($data) > 0) {
            $body = json_encode($data);
        }

        $result = $this->client->performHttpCall(self::REST_CREATE, $resource, $body);

        return new Link($result->href, $result->type);
    }

    protected function getResourcePageObject(int $count, PaginationLinks $links): BaseResourcePage
    {
        return new OrderCollection($this->client, $count, $links);
    }

    private function validateOrderId(string $orderId): void
    {
        if (empty($orderId) || strpos($orderId, self::RESOURCE_ID_PREFIX) !== 0) {
            throw new \InvalidArgumentException("Invalid order ID: '{$orderId}'. An order ID should start with '" . self::RESOURCE_ID_PREFIX . "'.");
        }
    }
}
