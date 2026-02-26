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
     * @example customer_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $customerId;

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
     * The associated order ID created from this refund (credit note)
     * @example order_66fc8a40718b46bea50f1a25f456d243
     */
    public ?string $orderId = null;

    /**
     * The associated original order ID from which this refund was created
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
     * Is this refund pending?
     */
    public function isPending(): bool
    {
        return $this->status === RefundStatus::PENDING;
    }

    /**
     * Is this refund completed?
     */
    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::COMPLETED;
    }

    /**
     * Is this refund failed?
     */
    public function isFailed(): bool
    {
        return $this->status === RefundStatus::FAILED;
    }

    /**
     * Is this refund canceled?
     */
    public function isCanceled(): bool
    {
        return $this->status === RefundStatus::CANCELED;
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
