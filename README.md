![Vatly Fluent PHP](art/banner.png)

# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)

Fluent billing flows for PHP, powered by [Vatly](https://vatly.com), the European Merchant of Record for AI, software and digital products.

> **Alpha — under active development. Every alpha release may include breaking changes. Pin exact versions during alpha.**

Create hosted checkouts, change subscriptions, and sync orders through verified webhooks. Fluent builds on `vatly/vatly-api-php` and lets you keep your framework and database.

## Install

Requires PHP 8.1+ and Composer. Keep your API key on the server.

```sh
composer require vatly/vatly-fluent-php:0.8.0-alpha.29 vatly/vatly-api-php:0.1.0-alpha.32
```

Both pins are needed because the underlying SDK is also alpha.

## Start a subscription checkout

After loading `vendor/autoload.php`, call this function with your key and authorized customer and plan IDs from the same storefront and mode:

```php
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Vatly;

function startSubscription(string $apiKey, string $customerId, string $planId): string
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

Redirect the browser to the returned URL. Confirm payment with [webhooks](docs/Webhooks.md), not the return page. Checkout builders work without storage wiring; add repositories when your application needs local billing records.

## Choose the right package

- **Laravel app:** use [vatly/vatly-laravel](https://github.com/vatly/vatly-laravel). It wires Fluent for you.
- **Plain PHP or another framework:** use this package for builders, subscription handles, customer bindings, and webhook persistence contracts. You supply routes, storage, transactions, and authorization.
- **Only direct API requests:** use [vatly/vatly-api-php](https://github.com/vatly/vatly-api-php). It provides the typed HTTP client without Fluent's integration layer.

## Documentation

Start with the [overview](docs/README.md), or read the [live PHP Fluent docs](https://docs.vatly.com/packages/fluent-php/readme).

- [Configuration](docs/Configuration.md)
- [Core concepts](docs/CoreConcepts.md)
- [Customers](docs/Customers.md)
- [Checkouts](docs/Checkouts.md)
- [Subscriptions](docs/Subscriptions.md)
- [Customer portal](docs/CustomerPortal.md)
- [Webhooks](docs/Webhooks.md)
- [Event flow](docs/webhook-flow.md)
- [Orders](docs/Orders.md)
- [Testing](docs/Testing.md)
- [Host tables](docs/host-owns-its-tables.md)

For request and response fields, see the [API reference](https://docs.vatly.com/api-reference).

## Contributing

Run `composer test` and `composer analyse`. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
