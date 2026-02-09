<?php

namespace Vatly\API\Endpoints;

use InvalidArgumentException;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Customer;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\Subscription;
use Vatly\API\Resources\SubscriptionCollection;
use Vatly\API\Types\Link;

class SubscriptionEndpoint extends BaseEndpoint
{
    protected string $resourcePath = "subscriptions";

    const RESOURCE_ID_PREFIX = 'subscription_';

    protected function getResourceObject(): Subscription
    {
        return new Subscription($this->client);
    }

    /**
     * @throws ApiException
     * @return Subscription|BaseResource
     */
    public function get(string $subscriptionId, array $parameters = []): BaseResource
    {
        $this->validateSubscriptionId($subscriptionId);

        return $this->rest_read($subscriptionId, $parameters);
    }

    public function listForCustomerId(string $customerId)
    {
        return $this->page(null, null, null, ['customer_id' => $customerId]);
    }

    public function listForCustomer(Customer $customerId)
    {
        return $this->listForCustomerId($customerId->id);
    }

    /**
     * @return SubscriptionCollection|BaseResourcePage
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
        return new SubscriptionCollection($this->client, $count, $links);
    }

    /**
     * @throws ApiException
     */
    public function update(string $subscriptionId, array $data = []): ?BaseResource
    {
        $this->validateSubscriptionId($subscriptionId);

        return $this->rest_update($subscriptionId, $data);
    }

    /**
     * Get the link to update the billing details (address and/or payment method) of a subscription.
     * You can use the $data parameter to prefill the form with the new billing address details.
     * @param string $subscriptionId The subscription's ID, for example: subscription_66fc8a40718b46bea50f1a25f456d243
     * @param array $data An array containing the new billing details.
     *
     * @return Link The link is used to redirect the customer to the website to update their billing details
     * @throws ApiException
     */
    public function requestLinkForBillingDetailsUpdate(string $subscriptionId, array $data = []): Link
    {
        $this->validateSubscriptionId($subscriptionId);

        $resource = "{$this->getResourcePath()}/" . urlencode($subscriptionId) . "/update-billing";

        $body = null;
        if (count($data) > 0) {
            $body = json_encode($data);
        }

        $result = $this->client->performHttpCall(self::REST_UPDATE, $resource, $body);

        return new Link($result->href, $result->type);
    }

    /**
     * @throws ApiException
     */
    public function cancel(string $subscriptionId, array $data = []): ?BaseResource
    {
        $this->validateSubscriptionId($subscriptionId);

        return $this->rest_delete($subscriptionId, $data);
    }

    /**
     * @param string $subscriptionId
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateSubscriptionId(string $subscriptionId): void
    {
        if (empty($subscriptionId) || strpos($subscriptionId, self::RESOURCE_ID_PREFIX) !== 0) {
            throw new InvalidArgumentException("Invalid subscription ID: '{$subscriptionId}'. A subscription ID should start with '" . self::RESOURCE_ID_PREFIX . "'.");
        }
    }
}
