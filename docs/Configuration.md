# Configuration

Install Fluent on your server with PHP 8.1 or newer and Composer. Keep API keys out of browser code and source control.

```sh
composer require vatly/vatly-fluent-php:0.8.0-alpha.29 vatly/vatly-api-php:0.1.0-alpha.32
```

> **Alpha — under active development. Every alpha release may include breaking changes. Pin exact versions during alpha.**

Install both pins: Composer's default stable-only policy otherwise rejects the SDK's transitive alpha dependency. These examples use this released pair.

## Set keys and return URLs

Load `vendor/autoload.php` at your application's entry point. Set `VATLY_API_KEY` and `VATLY_WEBHOOK_SECRET` in your server environment. Get a test key from the [dashboard](https://my.vatly.com).

```php
use Vatly\Fluent\Configuration\ArrayConfiguration;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

$config = new ArrayConfiguration([
    'api_key' => getenv('VATLY_API_KEY'),
    'webhook_secret' => getenv('VATLY_WEBHOOK_SECRET'),
    'redirect_url_success' => 'https://app.example.com/billing/return',
    'redirect_url_canceled' => 'https://app.example.com/billing/canceled',
]);
$vatly = new Vatly(new Wiring(config: $config));
```

`test_` keys use test mode; `live_` keys can charge real money. Keep products, customers, and local records separated by mode. Missing environment variables are configuration errors: validate them at application startup.

The default API URL is `https://api.vatly.com` and the version is `v1`. `ArrayConfiguration` also accepts `api_url` and `api_version`. Implement `ConfigurationInterface` instead if your framework already owns configuration.

## Add storage only when you need it

The instance above can create checkouts, operate on products, and open customer portal sessions. `Vatly::apiOnly($apiKey)` is the shortcut when you do not need redirect defaults.

`customers()` needs customer bindings. `subscription($localSubscription)` needs a subscription repository. `webhookProcessor()` needs subscription, order, binding, and webhook-call repositories plus an event dispatcher. Missing dependencies raise `IncompleteWiringException`; see [Core concepts](CoreConcepts.md) for the wiring example.

Subscription checkout and billing-update flows use configured redirect defaults. One-off `CheckoutBuilder::create()` takes both redirect URLs explicitly.

## Handle a failed API call

This function accepts the configured `$vatly`, an authorized customer ID, and a plan ID. Return a retry message to the customer when it returns `null`:

```php
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Exceptions\VatlyException;
use Vatly\Fluent\Vatly;

function tryCheckout(Vatly $vatly, string $customerId, string $planId): ?string
{
    try {
        $checkout = $vatly->subscriptionBuilder(new CustomerProfile(vatlyId: $customerId))
            ->toPlan($planId)->create();
        return $checkout->links->checkoutUrl->href;
    } catch (VatlyException $error) {
        error_log('Vatly checkout failed: ' . $error->getCode());
        return null;
    }
}
```

Fluent wraps SDK failures in `ApiCallFailedException`, which implements `VatlyException` and preserves the error code. `apiException()` gives you the original SDK exception. Calls made directly through `getApiClient()` use the SDK's own exception types instead.

See [Checkouts](Checkouts.md) next. For field details, see the [API reference](https://docs.vatly.com/api-reference).
