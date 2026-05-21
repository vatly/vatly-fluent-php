<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Webhooks\WebhookEventFactory;

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
    private ?GetOrder $getOrder = null;
    private ?CreateCheckout $createCheckout = null;
    private ?GetSubscription $getSubscription = null;
    private ?CancelSubscription $cancelSubscription = null;
    private ?SwapSubscriptionPlan $swapSubscriptionPlan = null;
    private ?UpdateSubscriptionBilling $updateSubscriptionBilling = null;

    public function __construct(
        string $apiKey,
    ) {
        $this->apiClient = new VatlyApiClient();
        $this->apiClient->setApiKey($apiKey);
        $this->signatureVerifier = new SignatureVerifier();
        $this->webhookEventFactory = new WebhookEventFactory($this->getOrder());
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

    public function getOrder(): GetOrder
    {
        return $this->getOrder ??= new GetOrder($this->apiClient);
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

    public function updateSubscriptionBilling(): UpdateSubscriptionBilling
    {
        return $this->updateSubscriptionBilling ??= new UpdateSubscriptionBilling($this->apiClient);
    }
}
