<?php

namespace Vatly\API\Endpoints;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\Order;
use Vatly\API\Resources\Refund;
use Vatly\API\Resources\RefundCollection;

class OrderRefundEndpoint extends BaseEndpoint
{
    protected string $resourcePath = "orders_refunds";

    protected function getResourceObject(): Refund
    {
        return new Refund($this->client);
    }

    protected function getResourcePageObject(int $count, PaginationLinks $links): RefundCollection
    {
        return new RefundCollection($this->client, $count, $links);
    }

    /**
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function getForOrderId(string $orderId, string $refundId, array $parameters = [])
    {
        $this->parentId = $orderId;

        return parent::rest_read($refundId, $parameters);
    }

    /**
     * @return BaseResourcePage|RefundCollection
     * @throws ApiException
     */
    public function pageForOrderId(
        string $orderId,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $parameters = []
    ) {
        $this->parentId = $orderId;

        return parent::rest_list($startingAfter, $endingBefore, $limit, $parameters);
    }

    /**
     * It creates a refund for an order.
     * @param Order $order
     * @param array $array
     * @param array $filters
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function createForOrder(Order $order, array $array, array $filters = [])
    {
        return $this->createForOrderId($order->id, $array, $filters);
    }

    /**
     * It creates a refund for an order id.
     * @param string $orderId The order's ID, for example: order_66fc8a40718b46bea50f1a25f456d243
     * @param array $data An array containing details of the refund.
     *
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function createForOrderId(string $orderId, array $data, array $filters = [])
    {
        $this->parentId = $orderId;

        return parent::rest_create($data, $filters);
    }

    /**
     * It creates a full refund for an order id.
     * @param string $orderId The order's ID, for example: order_66fc8a40718b46bea50f1a25f456d243
     * @param array $data An array containing details of the refund.
     *
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function createFullRefundForOrderId(string $orderId, array $data, array $filters = [])
    {
        $this->parentId = $orderId;
        $this->setResourcePath($this->resourcePath . '/full');

        return parent::rest_create($data, $filters);
    }

    /**
     * It cancels a refund for an order id.
     * It will throw an ApiException if the refund id is invalid or if the refund cannot be cancelled.
     * @return null
     * @throws ApiException
     */
    public function cancelRefundForOrderId(string $orderId, string $refundId)
    {
        $this->parentId = $orderId;

        return parent::rest_delete($refundId);
    }
}
