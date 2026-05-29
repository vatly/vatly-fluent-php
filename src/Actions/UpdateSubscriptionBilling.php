<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Exceptions\IncompleteInformationException;

class UpdateSubscriptionBilling extends BaseAction
{
    public function __construct(
        VatlyApiClient $vatlyApiClient,
        /** @readonly */
        private ConfigurationInterface $config,
    ) {
        parent::__construct($vatlyApiClient);
    }

    /**
     * Create a signed URL where the customer can update the billing details for a subscription
     * (billing address, VAT number, company name) via a hosted flow.
     *
     * Missing `redirectUrlSuccess` / `redirectUrlCanceled` keys are filled in from
     * {@see ConfigurationInterface::getDefaultRedirectUrlSuccess()} and
     * {@see ConfigurationInterface::getDefaultRedirectUrlCanceled()}; caller-supplied
     * values always win. If neither the caller nor the config provides a value,
     * an {@see IncompleteInformationException} is thrown.
     *
     * @param string $subscriptionId The subscription ID (e.g., subscription_xxx)
     * @param array<string, mixed> $prefillData May override `redirectUrlSuccess` /
     *                                          `redirectUrlCanceled`, and may include
     *                                          `billingAddress` as an optional prefill.
     *
     * @throws IncompleteInformationException When a required redirect URL resolves to an empty string.
     */
    public function execute(string $subscriptionId, array $prefillData = []): Link
    {
        $payload = $prefillData + [
            'redirectUrlSuccess' => $this->config->getDefaultRedirectUrlSuccess(),
            'redirectUrlCanceled' => $this->config->getDefaultRedirectUrlCanceled(),
        ];

        foreach (['redirectUrlSuccess', 'redirectUrlCanceled'] as $required) {
            if (($payload[$required] ?? '') === '') {
                throw IncompleteInformationException::missingBillingRedirectUrl($required);
            }
        }

        return $this->vatlyApiClient->subscriptions->updateBilling(
            $subscriptionId,
            $payload,
        );
    }
}
