<?php

declare(strict_types=1);

namespace Vatly;

use Vatly\Actions\CancelSubscription;
use Vatly\Actions\CreateCheckout;
use Vatly\Actions\CreateCustomer;
use Vatly\Actions\GetCustomer;
use Vatly\Actions\GetSubscription;
use Vatly\Actions\SwapSubscriptionPlan;
use Vatly\API\VatlyApiClient;
use Vatly\Webhooks\SignatureVerifier;
use Vatly\Webhooks\WebhookEventFactory;

/**
 * Main entry point for the Vatly SDK.
 *
 * This class provides access to all Vatly operations and can be used
 * in any PHP application without framework dependencies.
 */
class Vatly
{
    private VatlyApiClient $apiClient;
    private SignatureVerifier $signatureVerifier;
    private WebhookEventFactory $webhookEventFactory;

    // Lazy-loaded actions
    private ?CreateCustomer $createCustomer = null;
    private ?GetCustomer $getCustomer = null;
    private ?CreateCheckout $createCheckout = null;
    private ?GetSubscription $getSubscription = null;
    private ?CancelSubscription $cancelSubscription = null;
    private ?SwapSubscriptionPlan $swapSubscriptionPlan = null;

    public function __construct(
        string $apiKey,
        string $apiUrl = 'https://api.vatly.com',
        string $apiVersion = 'v1',
    ) {
        $this->apiClient = new VatlyApiClient($apiKey, $apiUrl, $apiVersion);
        $this->signatureVerifier = new SignatureVerifier();
        $this->webhookEventFactory = new WebhookEventFactory();
    }

    /**
     * Get the API client for direct API access.
     */
    public function getApiClient(): VatlyApiClient
    {
        return $this->apiClient;
    }

    /**
     * Get the signature verifier for webhook validation.
     */
    public function getSignatureVerifier(): SignatureVerifier
    {
        return $this->signatureVerifier;
    }

    /**
     * Get the webhook event factory.
     */
    public function getWebhookEventFactory(): WebhookEventFactory
    {
        return $this->webhookEventFactory;
    }

    // Action accessors

    public function createCustomer(): CreateCustomer
    {
        return $this->createCustomer ??= new CreateCustomer($this->apiClient);
    }

    public function getCustomer(): GetCustomer
    {
        return $this->getCustomer ??= new GetCustomer($this->apiClient);
    }

    public function createCheckout(): CreateCheckout
    {
        return $this->createCheckout ??= new CreateCheckout($this->apiClient);
    }

    public function getSubscription(): GetSubscription
    {
        return $this->getSubscription ??= new GetSubscription($this->apiClient);
    }

    public function cancelSubscription(): CancelSubscription
    {
        return $this->cancelSubscription ??= new CancelSubscription($this->apiClient);
    }

    public function swapSubscriptionPlan(): SwapSubscriptionPlan
    {
        return $this->swapSubscriptionPlan ??= new SwapSubscriptionPlan($this->apiClient);
    }
}
