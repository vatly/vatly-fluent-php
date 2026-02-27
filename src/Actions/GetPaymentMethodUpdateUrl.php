<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Types\Link;

class GetPaymentMethodUpdateUrl extends BaseAction
{
    /**
     * Get the URL where a customer can update their payment method for a subscription.
     *
     * @param string $subscriptionId The subscription ID (e.g., subscription_xxx)
     * @param array<string, mixed> $prefillData Optional data to prefill the form (billing address, etc.)
     */
    public function execute(string $subscriptionId, array $prefillData = []): Link
    {
        return $this->vatlyApiClient->subscriptions->requestLinkForBillingDetailsUpdate(
            $subscriptionId,
            $prefillData
        );
    }
}
