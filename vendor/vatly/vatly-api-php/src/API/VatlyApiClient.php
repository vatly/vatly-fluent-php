<?php

declare(strict_types=1);

namespace Vatly\API;

use Vatly\API\Endpoints\ChargebackEndpoint;
use Vatly\API\Endpoints\CheckoutEndpoint;
use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Endpoints\CustomerSubscriptionEndpoint;
use Vatly\API\Endpoints\OneOffProductEndpoint;
use Vatly\API\Endpoints\OrderChargebackEndpoint;
use Vatly\API\Endpoints\OrderEndpoint;
use Vatly\API\Endpoints\OrderRefundEndpoint;
use Vatly\API\Endpoints\RefundEndpoint;
use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\Endpoints\SubscriptionPlanEndpoint;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Exceptions\HttpAdapterDoesNotSupportDebuggingException;
use Vatly\API\HttpClient\DefaultHttpClientFactory;
use Vatly\API\HttpClient\HttpClientFactoryInterface;
use Vatly\API\HttpClient\HttpClientInterface;

class VatlyApiClient
{
    /**
     * The version of this client.
     */
    public const CLIENT_VERSION = '0.0.1';

    /**
     * Endpoint of the remote API.
     */
    public const API_ENDPOINT = 'https://api.vatly.com';

    /**
     * Version of the remote API.
     */
    public const API_VERSION = "v1";

    /**
     * HTTP Methods
     */
    public const HTTP_GET = "GET";
    public const HTTP_POST = "POST";
    public const HTTP_DELETE = "DELETE";
    public const HTTP_PATCH = "PATCH";

    /**
     * HTTP status code for an empty ok response.
     */
    public const HTTP_NO_CONTENT = 204;

    /**
     * @var \Vatly\API\HttpClient\HttpClientInterface
     */
    protected HttpClientInterface $httpClient;

    /**
     * @var string[]
     */
    protected array $versionStrings;

    protected string $apiEndpoint = self::API_ENDPOINT;

    protected string $apiVersion = self::API_VERSION;

    protected ?string $apiKey;

    public CheckoutEndpoint $checkouts;

    public OrderEndpoint $orders;

    public OneOffProductEndpoint $oneOffProducts;
    public SubscriptionPlanEndpoint $subscriptionPlans;

    public CustomerEndpoint $customers;
    public CustomerSubscriptionEndpoint $customerSubscriptions;
    public ChargebackEndpoint $chargebacks;
    public OrderChargebackEndpoint $orderChargebacks;
    public RefundEndpoint $refunds;
    public OrderRefundEndpoint $orderRefunds;
    public SubscriptionEndpoint $subscriptions;

    /**
     * @throws \Vatly\API\Exceptions\IncompatiblePlatformException
     */
    public function __construct(
        ?HttpClientFactoryInterface $httpClientFactory = null,
        ?CompatibilityChecker $compatibilityChecker = null
    ) {
        if (! $compatibilityChecker) {
            $compatibilityChecker = new CompatibilityChecker;
        }
        $compatibilityChecker->checkCompatibility();


        if ($httpClientFactory) {
            $this->httpClient = $httpClientFactory->make();
        } else {
            $this->httpClient = (new DefaultHttpClientFactory)->make();
        }

        $this->initializeVersionString();
        $this->initializeEndpoints();
    }

    protected function initializeEndpoints(): void
    {
        $this->checkouts = new CheckoutEndpoint($this);
        $this->orders = new OrderEndpoint($this);
        $this->oneOffProducts = new OneOffProductEndpoint($this);
        $this->subscriptionPlans = new SubscriptionPlanEndpoint($this);
        $this->customers = new CustomerEndpoint($this);
        $this->chargebacks = new ChargebackEndpoint($this);
        $this->orderChargebacks = new OrderChargebackEndpoint($this);
        $this->refunds = new RefundEndpoint($this);
        $this->orderRefunds = new OrderRefundEndpoint($this);
        $this->subscriptions = new SubscriptionEndpoint($this);
        $this->customerSubscriptions = new CustomerSubscriptionEndpoint($this);
    }

    protected function initializeVersionString(): void
    {
        $this->addVersionString('Vatly/'.self::CLIENT_VERSION);
        $this->addVersionString('PHP/'.phpversion());
        $this->addVersionString($this->httpClient->versionString());
    }

    /**
     * @return string[]
     */
    public function getVersionStrings(): array
    {
        return $this->versionStrings;
    }

    public function addVersionString(string $versionString): void
    {
        $this->versionStrings[] = str_replace([' ', "\t", "\n", "\r"], '-', $versionString);
    }

    public function setApiEndpoint(string $url): VatlyApiClient
    {
        $this->apiEndpoint = rtrim(trim($url), '/');

        return $this;
    }

    public function getApiEndpoint(): string
    {
        return $this->apiEndpoint;
    }

    public function setApiVersion(string $version): VatlyApiClient
    {
        $this->apiVersion = $version;

        return $this;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function setApiKey(string $apiKey): VatlyApiClient
    {
        $apiKey = trim($apiKey);

        if (! preg_match('/^(live|test)_[\w|]{18,}$/', $apiKey)) {
            throw new ApiException("Invalid API key: '{$apiKey}'. An API key must start with 'test_' or 'live_' and must be at least 18 characters long.");
        }

        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Perform a http call. This method is used by the resource specific classes.
     *
     * @param string $httpMethod
     * @param string $apiMethod
     * @param string|null $httpBody
     *
     * @return \stdClass
     * @throws ApiException
     */
    public function performHttpCall(string $httpMethod, string $apiMethod, ?string $httpBody = null): ?object
    {
        $url = $this->apiEndpoint . "/" . self::API_VERSION . "/" . $apiMethod;

        return $this->performHttpCallToFullUrl($httpMethod, $url, $httpBody);
    }

    /**
     * Perform a http call to a full url. This method is used by the resource specific classes.
     *
     * @param $httpMethod
     * @param $url
     * @param $httpBody
     * @return object|null
     * @throws \Vatly\API\Exceptions\ApiException
     */
    public function performHttpCallToFullUrl(string $httpMethod, string $url, ?string $httpBody = null): ?object
    {
        if (empty($this->apiKey)) {
            throw new ApiException("You have not set an API key. Please use setApiKey() to set the API key.");
        }

        $headers = [
            'Accept' => "application/json",
            'Authorization' => "Bearer {$this->apiKey}",
            'User-Agent' => implode(' ', $this->versionStrings),
        ];

        if ($httpBody !== null) {
            $headers['Content-Type'] = "application/json";
        }

        if (function_exists("php_uname")) {
            $headers['X-Vatly-Client-Info'] = php_uname();
        }

        return $this->httpClient->send($httpMethod, $url, $headers, $httpBody);
    }

    /**
     * Enable debugging mode. If debugging mode is enabled, the attempted request will be included in the ApiException.
     * By default, debugging is disabled to prevent leaking sensitive request data into exception logs.
     *
     * @throws \Vatly\Api\Exceptions\HttpAdapterDoesNotSupportDebuggingException
     */
    public function enableDebugging(): void
    {
        if (
            ! method_exists($this->httpClient, 'supportsDebugging')
            || ! $this->httpClient->supportsDebugging()
        ) {
            throw new HttpAdapterDoesNotSupportDebuggingException(
                "Debugging is not supported by " . get_class($this->httpClient) . "."
            );
        }

        $this->httpClient->enableDebugging();
    }

    /**
     * Disable debugging mode. If debugging mode is enabled, the attempted request will be included in the ApiException.
     * By default, debugging is disabled to prevent leaking sensitive request data into exception logs.
     *
     * @throws \Vatly\Api\Exceptions\HttpAdapterDoesNotSupportDebuggingException
     */
    public function disableDebugging(): void
    {
        if (
            ! method_exists($this->httpClient, 'supportsDebugging')
            || ! $this->httpClient->supportsDebugging()
        ) {
            throw new HttpAdapterDoesNotSupportDebuggingException(
                "Debugging is not supported by " . get_class($this->httpClient) . "."
            );
        }

        $this->httpClient->disableDebugging();
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }
}
