<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCustomer;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\ResumeSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Exceptions\IncompleteWiringException;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Webhooks\WebhookProcessor;
use Vatly\Fluent\Webhooks\WebhookProcessorFactory;

/**
 * Composition root for the Vatly SDK.
 *
 * Drivers (Laravel, etc.) construct a single instance — typically a singleton
 * — from a {@see Wiring} DTO that supplies the configuration and the driver's
 * concrete contract implementations (repositories, event dispatcher). Every
 * other fluent service (`CustomerService`, `SubscriptionHandle`, `OrderHandle`,
 * `WebhookProcessor`, etc.) resolves lazily through methods on this class.
 *
 * For non-driver scripts that only need to hit the API, see {@see self::apiOnly()}.
 */
class Vatly
{
    private VatlyApiClient $apiClient;

    // Lazy-loaded actions
    private ?CreateCustomer $createCustomer = null;
    private ?GetCustomer $getCustomer = null;
    private ?GetOrder $getOrder = null;
    private ?CreateCheckout $createCheckout = null;
    private ?GetSubscription $getSubscription = null;
    private ?CancelSubscription $cancelSubscription = null;
    private ?ResumeSubscription $resumeSubscription = null;
    private ?SwapSubscriptionPlan $swapSubscriptionPlan = null;
    private ?UpdateSubscriptionBilling $updateSubscriptionBilling = null;

    // Lazy-loaded composed services
    private ?CustomerService $customers = null;
    private ?WebhookProcessor $webhookProcessor = null;
    private ?WebhookEventFactory $webhookEventFactory = null;
    private ?SignatureVerifier $signatureVerifier = null;

    public function __construct(
        private readonly Wiring $wiring,
    ) {
        $config = $wiring->config;

        $this->apiClient = new VatlyApiClient();
        $this->apiClient->setApiKey($config->getApiKey());
        $this->apiClient->setApiEndpoint($config->getApiUrl());
        $this->apiClient->setApiVersion($config->getApiVersion());
    }

    /**
     * Quick-start constructor for non-driver consumers that only need to
     * hit the API. Equivalent to `new Vatly(new Wiring(new ArrayConfiguration(...)))`.
     *
     * Calling repository-dependent methods on the returned instance throws
     * {@see IncompleteWiringException}.
     */
    public static function apiOnly(string $apiKey): self
    {
        return new self(new Wiring(
            config: new ArrayConfiguration(['api_key' => $apiKey]),
        ));
    }

    public function getWiring(): Wiring
    {
        return $this->wiring;
    }

    public function getApiClient(): VatlyApiClient
    {
        return $this->apiClient;
    }

    // --- Webhook helpers ---

    public function getSignatureVerifier(): SignatureVerifier
    {
        return $this->signatureVerifier ??= new SignatureVerifier();
    }

    public function getWebhookEventFactory(): WebhookEventFactory
    {
        return $this->webhookEventFactory ??= new WebhookEventFactory($this->getOrder());
    }

    public function webhookProcessor(): WebhookProcessor
    {
        return $this->webhookProcessor ??= WebhookProcessorFactory::create(
            config: $this->wiring->config,
            subscriptions: $this->wiring->subscriptions
                ?? throw IncompleteWiringException::missing('subscriptions', 'WebhookProcessor'),
            orders: $this->wiring->orders
                ?? throw IncompleteWiringException::missing('orders', 'WebhookProcessor'),
            webhookCalls: $this->wiring->webhookCalls
                ?? throw IncompleteWiringException::missing('webhookCalls', 'WebhookProcessor'),
            dispatcher: $this->wiring->events
                ?? throw IncompleteWiringException::missing('events', 'WebhookProcessor'),
            bindings: $this->wiring->customerBindings
                ?? throw IncompleteWiringException::missing('customerBindings', 'WebhookProcessor'),
            getOrder: $this->getOrder(),
            additionalReactions: $this->wiring->additionalWebhookReactions,
        );
    }

    // --- Customer composition ---

    public function customers(): CustomerService
    {
        return $this->customers ??= new CustomerService(
            createCustomer: $this->createCustomer(),
            getCustomer: $this->getCustomer(),
            bindings: $this->wiring->customerBindings
                ?? throw IncompleteWiringException::missing('customerBindings', 'CustomerService'),
        );
    }

    // --- Builders (per-call construction; no caching) ---

    public function checkoutBuilder(CustomerProfile $profile): CheckoutBuilder
    {
        return new CheckoutBuilder(
            customer: $profile,
            createCheckout: $this->createCheckout(),
        );
    }

    public function subscriptionBuilder(CustomerProfile $profile): SubscriptionBuilder
    {
        return new SubscriptionBuilder(
            config: $this->wiring->config,
            customer: $profile,
            checkoutBuilder: $this->checkoutBuilder($profile),
        );
    }

    /**
     * Build a {@see SubscriptionHandle} wrapping a persistent subscription.
     *
     * Drivers use this so their Eloquent (or equivalent) Subscription model
     * can expose Cashier-style operation methods that delegate here.
     */
    public function subscription(SubscriptionInterface $subscription): SubscriptionHandle
    {
        return new SubscriptionHandle(
            subscription: $subscription,
            subscriptions: $this->wiring->subscriptions
                ?? throw IncompleteWiringException::missing('subscriptions', 'SubscriptionHandle'),
            swapAction: $this->swapSubscriptionPlan(),
            cancelAction: $this->cancelSubscription(),
            resumeAction: $this->resumeSubscription(),
            getSubscriptionAction: $this->getSubscription(),
            updateBillingAction: $this->updateSubscriptionBilling(),
        );
    }

    /**
     * Build an {@see OrderHandle} wrapping a persistent order.
     *
     * Drivers use this so their Eloquent (or equivalent) Order model can
     * expose operation methods (e.g. `invoiceUrl()`) that delegate here.
     */
    public function order(OrderInterface $order): OrderHandle
    {
        return new OrderHandle(
            order: $order,
            getOrderAction: $this->getOrder(),
        );
    }

    // --- Action accessors ---

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

    public function resumeSubscription(): ResumeSubscription
    {
        return $this->resumeSubscription ??= new ResumeSubscription($this->apiClient);
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
