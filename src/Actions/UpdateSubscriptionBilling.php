<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Types\Link;

class UpdateSubscriptionBilling extends BaseAction
{
    /**
     * Create a signed URL where the customer can update the billing details for a subscription
     * (billing address, VAT number, company name) via a hosted flow.
     *
     * @param string $subscriptionId The subscription ID (e.g., subscription_xxx)
     * @param array<string, mixed> $prefillData Must include `redirectUrlSuccess` and
     *                                          `redirectUrlCanceled` (required by the API; the
     *                                          SDK does not merge in config defaults here).
     *                                          May include `billingAddress` as an optional
     *                                          prefill.
     */
    public function execute(string $subscriptionId, array $prefillData = []): Link
    {
        return $this->vatlyApiClient->subscriptions->updateBilling(
            $subscriptionId,
            $prefillData
        );
    }
}
