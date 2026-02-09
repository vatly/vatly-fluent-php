<?php

namespace Vatly\API\Resources;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Links\OrderLinks;
use Vatly\API\Types\Address;
use Vatly\API\Types\Link;
use Vatly\API\Types\Money;
use Vatly\API\Types\OrderStatus;
use Vatly\API\Types\TaxesCollection;

class Order extends BaseResource
{
    /**
     * @example order_66fc8a40718b46bea50f1a25f456d243
     */
    public string $id;

    /**
     * @example order
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

    public Money $total;

    public Money $subtotal;

    public TaxesCollection $taxes;

    /**
     * @example creditcard
     */
    public string $paymentMethod;

    public ?string $invoiceNumber = null;

    /** @see OrderStatus */
    public string $status;

    public bool $cancelled = false;

    public OrderLinks $links;

    public Address $customerDetails;

    public Address $merchantDetails;

    /**
     * @var OrderLine[]|array
     */
    public array $lines;


    /**
     * Get the line value objects
     *
     * @return OrderLineCollection
     */
    public function lines(): OrderLineCollection
    {
        return ResourceFactory::createCursorResourceCollection(
            $this->apiClient,
            $this->lines,
            OrderLine::class,
            null,
            OrderLineCollection::class,
        );
    }

    /**
     * Is this order created?
     */
    public function isCreated(): bool
    {
        return $this->status === OrderStatus::STATUS_CREATED;
    }

    /**
     * Is this order paid for?
     */
    public function isPaid(): bool
    {
        return $this->status === OrderStatus::STATUS_PAID;
    }

    /**
     * Is this order canceled?
     */
    public function isCanceled(): bool
    {
        return $this->status === OrderStatus::STATUS_CANCELED;
    }

    /**
     * Is this order completed?
     */
    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::STATUS_COMPLETED;
    }

    /**
     * Is this order expired?
     */
    public function isExpired(): bool
    {
        return $this->status === OrderStatus::STATUS_EXPIRED;
    }

    /**
     * Is this order completed?
     */
    public function isPending(): bool
    {
        return $this->status === OrderStatus::STATUS_PENDING;
    }

    /**
     * @param array $data
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function refund(array $data)
    {
        return $this->apiClient->orderRefunds->createForOrderId($this->id, $data);
    }

    /**
     * @param array $data
     * @return BaseResource|Refund
     * @throws ApiException
     */
    public function fullRefund(array $data)
    {
        return $this->apiClient->orderRefunds->createFullRefundForOrderId($this->id, $data);
    }

    public function requestAddressUpdateLink(array $data = []): Link
    {
        return $this->apiClient->orders->requestAddressUpdateLink($this->id, $data);
    }
}
