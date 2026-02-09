<?php

namespace Vatly\API\Resources;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Links\RefundLinks;
use Vatly\API\Types\Money;
use Vatly\API\Types\RefundStatus;
use Vatly\API\Types\TaxesCollection;

class Refund extends BaseResource
{
    /**
     * @example refund_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example refund
     */
    public string $resource;

    /**
     * @example merchant_f7f3cbf96f6c444abd76aafaf99ecde9
     */
    public string $merchantId;

    /**
     * @example 2023-08-11T10:48:51+02:00
     */
    public string $createdAt;

    public bool $testmode;

    /** @see RefundStatus */
    public string $status;

    public Money $total;

    public Money $subtotal;

    public TaxesCollection $taxes;

    /**
     * @var RefundLine[]|array
     */
    public array $lines;

    public RefundLinks $links;

    /**
     * The associated order ID created from this refund
     * @example order_66fc8a40718b46bea50f1a25f456d243
     */
    public ?string $orderId = null;

    /**
     * The associated original refund ID from which this refund was created
     * @example order_66fc8a40718b46bea50f1a25f456d242
     */
    public string $originalOrderId;

    public function lines(): RefundLineCollection
    {
        return ResourceFactory::createCursorResourceCollection(
            $this->apiClient,
            $this->lines,
            RefundLine::class,
            null,
            RefundLineCollection::class,
        );
    }

    /**
     * @throws ApiException
     * @return null
     */
    public function cancel()
    {
        $this->apiClient->orderRefunds->cancelRefundForOrderId($this->originalOrderId, $this->id);

        return null;
    }
}
