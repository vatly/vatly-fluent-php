# Subscriptions

Change a subscription using a local record that implements `SubscriptionInterface`. These examples assume `$vatly` has your subscription repository wired and `$localSubscription` belongs to the signed-in account.

## Change the plan

Choose one policy for a plan change; do not call all three for the same request:

```php
$subscription = $vatly->subscription($localSubscription);
$subscription->swap('subscription_plan_pro');
$pending = $subscription->scheduledUpdate();
```

`swap()` schedules the change for the next billing cycle by default. The current plan remains in effect until then. `scheduledUpdate()` makes a live API call and returns a nullable `ScheduledSubscriptionUpdate`; read its `subscriptionPlanId`, `quantity`, and `effectiveAt` to show the pending change. `hasScheduledUpdate()` also makes a live call, so do not call both just to get the same data.

To apply the change now and invoice the prorated difference:

```php
$vatly->subscription($localSubscription)->swapAndInvoice('subscription_plan_pro');
```

To apply it now without charging for the change:

```php
$vatly->subscription($localSubscription)->swapWithoutCharge('subscription_plan_pro');
```

The next renewal uses the new price. Each method also accepts an options array for API update fields. Fluent updates local plan and quantity from the API response; webhooks reconcile subsequent changes. Use the [API reference](https://docs.vatly.com/api-reference) for proration and update options.

## Cancel and resume

Request cancellation:

```php
$vatly->subscription($localSubscription)->cancel();
```

`cancel()` takes no options and does not set the local end date itself. The cancellation webhook carries the confirmed end date. Keep access through a valid grace period; do not revoke access just because a cancellation was requested.

While the local subscription is on its grace period, reverse the pending cancellation:

```php
$subscription = $vatly->subscription($localSubscription);
if ($subscription->isOnGracePeriod()) {
    $subscription->resume();
}
```

`resume()` clears the local end date after the API succeeds. It is not a way to restart an already-ended subscription. `cancellationReason()` fetches the live reason, such as `customer_request`, `merchant_request`, or `payment_failure`.

## Update billing

Send the customer to Vatly to change billing details, including their address or VAT number:

```php
$url = $vatly->subscription($localSubscription)->updateBilling([
    'billingAddress' => [
        'companyName' => 'Example BV',
        'country' => 'NL',
    ],
]);
```

Redirect to `$url`. Each call returns a fresh time-bounded link; do not cache it. Redirect defaults come from configuration, and the array can override `redirectUrlSuccess` and `redirectUrlCanceled`. Missing URLs raise `IncompleteInformationException`.

The `subscription.billing_updated` reaction refreshes the locally stored mandate summary. The [customer portal](CustomerPortal.md) is an alternative for broader self-service billing.

## Read local state carefully

`isValid()`, `isOnGracePeriod()`, and `isEnded()` delegate to your local model. They are only as current as your webhook handling. The optional `DerivesSubscriptionState` trait derives them from the end date, not payment status. Handle payment recovery and entitlement rules explicitly; see [Event flow](webhook-flow.md).
