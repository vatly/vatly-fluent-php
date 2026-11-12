# Getting started

Sell a download, start a subscription, and keep your app's billing records in step with Vatly.
Vatly is the European Merchant of Record for AI, software and digital products.

> **Alpha — under active development. Every alpha release may include breaking changes. Pin exact versions during alpha.**

Fluent adds checkout builders, subscription operations, customer bindings, and a webhook processing pipeline to `vatly/vatly-api-php`. You keep your database and framework. Fluent calls your storage contracts to save orders and subscriptions.

## Start a subscription

Install the package using [Configuration](Configuration.md). This function creates a hosted checkout and returns the URL to send to the browser:

```php
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Vatly;

function subscriptionCheckout(string $apiKey, string $customerId, string $planId): string
{
    $vatly = Vatly::apiOnly($apiKey);
    $checkout = $vatly->subscriptionBuilder(new CustomerProfile(vatlyId: $customerId))
        ->toPlan($planId)
        ->withRedirectUrlSuccess('https://app.example.com/billing/return')
        ->withRedirectUrlCanceled('https://app.example.com/billing/canceled')
        ->create();

    return $checkout->links->checkoutUrl->href;
}
```

Use a customer and plan from the same storefront and mode as your key. Authorize the signed-in user's access to that customer first. Redirect to the returned URL, then use [Webhooks](Webhooks.md) to confirm the subscription and grant access. The return page alone is not proof of payment.

## Choose your layer

| You need | Use |
| --- | --- |
| Billing in a Laravel app | [vatly-laravel](https://github.com/vatly/vatly-laravel). It wires Fluent into Laravel for you. |
| Fluent billing flows in plain PHP, Symfony, WordPress, or a custom driver | This package. Start without storage; add repositories when you need local records. |
| A few direct API calls, without local billing state | [vatly-api-php](https://docs.vatly.com/packages/php/readme). No Fluent layer required. |

With the raw SDK you assemble checkout payloads and coordinate webhook persistence yourself. Fluent gives those common tasks names: `toPlan()`, `swapAndInvoice()`, `updateBilling()`, and `webhookProcessor()->handle()`. It does not supply tables, routes, authorization, transactions, or exactly-once delivery.

## Guides

| Task | Page |
| --- | --- |
| Install, set keys, redirects, and handle errors | [Configuration](Configuration.md) |
| Connect billables and storage | [Core concepts](CoreConcepts.md) |
| Link your users to Vatly customers | [Customers](Customers.md) |
| Sell one-off products and subscriptions | [Checkouts](Checkouts.md) |
| Swap, cancel, resume, and update billing | [Subscriptions](Subscriptions.md) |
| Open hosted self-service billing | [Customer portal](CustomerPortal.md) |
| Verify deliveries and handle retries safely | [Webhooks](Webhooks.md) |
| Choose which events to persist or listen for | [Event flow](webhook-flow.md) |
| Show invoices, lines, refunds, and chargebacks | [Orders](Orders.md) |
| Test without charging a customer | [Testing](Testing.md) |
| Reuse your existing order and subscription tables | [Host tables](host-owns-its-tables.md) |

For request and response fields, see the [API reference](https://docs.vatly.com/api-reference).
