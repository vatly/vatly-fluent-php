<?php

namespace Vatly\API\Resources;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Links\CustomerLinks;

class Customer extends BaseResource
{
    /**
     * @example customer_78b146a7de7d417e9d68d7e6ef193d18
     */
    public string $id;

    /**
     * @example customer
     */
    public string $resource;

    public ?string $name = null;

    public ?string $streetAndNumber = null;

    public ?string $streetAdditional = null;

    public ?string $postalCode = null;

    public ?string $city = null;

    public ?string $region = null;

    public ?string $countryCode = null;

    public ?string $country = null;

    public ?string $companyName = null;

    public ?string $vatNumber = null;

    public ?string $email = null;

    public ?string $locale = null;

    public ?string $createdAt = null;

    public bool $testmode;

    /**
     * @var array|object|null
     * @example ["customer_id" => "123456"]
     */
    public $metadata;

    public CustomerLinks $links;

    /**
     * @throws ApiException
     */
    public function subscriptions()
    {
        return $this->apiClient->customerSubscriptions->pageForCustomerId($this->id);
    }

    /**
     * @throws ApiException
     */
    public function subscription(string $subscriptionId, array $parameters = [])
    {
        return $this->apiClient->customerSubscriptions->getForCustomerId($this->id, $subscriptionId, $parameters);
    }
}
