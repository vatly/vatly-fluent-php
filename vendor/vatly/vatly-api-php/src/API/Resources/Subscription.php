<?php

namespace Vatly\API\Resources;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Links\SubscriptionLinks;
use Vatly\API\Types\Address;
use Vatly\API\Types\Link;
use Vatly\API\Types\Money;
use Vatly\API\Types\SubscriptionStatus;

class Subscription extends BaseResource
{
    /**
     * @example subscription_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example subscription
     */
    public string $resource;

    /**
     * @example customer_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $customerId;

    /**
     * @example subscription_plan_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $subscriptionPlanId;

    public bool $testmode;

    public string $name;

    public string $description;

    public Address $billingAddress;

    /**
     * Price before any taxes and/or discounts are applied.
     */
    public Money $basePrice;

    public int $quantity;

    public string $interval;

    public int $intervalCount;


    /** @see SubscriptionStatus */
    public string $status;

    public string $startedAt;

    public ?string $endedAt;

    public ?string $cancelledAt;

    public ?string $renewedAt;

    public ?string $renewedUntil;

    public ?string $nextRenewalAt;

    public ?string $trialEndAt;

    public ?int $trialDays;

    public SubscriptionLinks $links;

    /**
     * @return Subscription|BaseResource
     * @throws ApiException
     */
    public function update(array $data = []): BaseResource
    {
        return $this->apiClient->subscriptions->update($this->id, $data);
    }

    /**
     * @param array $data An array containing the new billing details.
     * @return Link The link is used to redirect the customer to the website to update their billing details.
     * @throws ApiException
     */
    public function requestLinkForBillingDetailsUpdate(array $data = []): Link
    {
        return $this->apiClient->subscriptions->requestLinkForBillingDetailsUpdate($this->id, $data);
    }

    /**
     * @throws ApiException
     */
    public function cancel(array $data = []): ?BaseResource
    {
        return $this->apiClient->subscriptions->cancel($this->id, $data);
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    public function isOnGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::ON_GRACE_PERIOD;
    }

    public function isTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIAL;
    }
}
