# Checkouts

Create a hosted payment page, then redirect the browser to its URL. These examples use a configured `$vatly` and a customer your signed-in user is allowed to use.

## Sell a one-off product

```php
use Vatly\Fluent\CustomerProfile;

$checkout = $vatly->checkoutBuilder(new CustomerProfile(vatlyId: 'customer_example'))
    ->withLocale('nl')
    ->withMetadata(['host_order_id' => 'order_42'])
    ->create(
        [['id' => 'one_off_product_example', 'quantity' => 2]],
        'https://app.example.com/orders/return',
        'https://app.example.com/cart',
    );
$url = $checkout->links->checkoutUrl->href;
```

Replace the example product and customer IDs with records from your storefront and mode. Product IDs refer to configured products, not names or arbitrary SKUs. Create products in the dashboard or through `oneOffProducts()` and `subscriptionPlans()`.

The `create()` URLs are required even if you set configuration defaults. Build a fresh builder for each checkout; reusing one can accumulate items. Metadata should contain server-chosen routing IDs, not secrets or untrusted ownership claims.

## Start a subscription

Set redirect defaults in [Configuration](Configuration.md), or override them on the builder:

```php
use Vatly\Fluent\CustomerProfile;

$checkout = $vatly->subscriptionBuilder(new CustomerProfile(vatlyId: 'customer_example'))
    ->toPlan('subscription_plan_example')
    ->withQuantity(3)
    ->withTrialDays(14)
    ->withBillingAddress([
        'companyName' => 'Example BV',
        'city' => 'Amsterdam',
        'country' => 'NL',
    ])
    ->create(['metadata' => ['host_account_id' => 'team_42']]);
$url = $checkout->links->checkoutUrl->href;
```

The result is a checkout, not a local subscription. `subscription.started` creates the local record through your repository. `withTrialDays()` requires a positive whole number. `withTrialEndsAt()` rounds a future end time up to whole days. If you omit the trial, the API applies any plan-level default.

Both builders support `withLocale()` and `withBillingAddress()`. Address values prefill hosted fields; the customer can still edit them. A supplied country takes precedence over IP detection. A supplied tax ID is registered only when checkout is submitted.

## Override a price

Use decimal strings, not floats or integer cents, in an API product price:

```php
use Vatly\Fluent\CustomerProfile;

$checkout = $vatly->checkoutBuilder(new CustomerProfile(vatlyId: 'customer_example'))
    ->create(
        [[
            'id' => 'one_off_product_example',
            'quantity' => 1,
            'price' => ['value' => '49.99', 'currency' => 'EUR'],
        ]],
        'https://app.example.com/orders/return',
        'https://app.example.com/cart',
    );
```

Choose amounts on the server. An override still needs a configured product. Use the [API reference](https://docs.vatly.com/api-reference) for allowed product combinations and fields.

## Confirm payment, not the redirect

Keep the checkout ID against your server-side cart or session. The success page may load before a webhook, or never load at all. Confirm payment through [Webhooks](Webhooks.md), then fulfill once. `checkout.paid` is useful for checkout analytics; `order.paid` supplies the order and tax data for persistence and fulfillment.
