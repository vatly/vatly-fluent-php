# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)

> **Alpha release -- under active development. Expect breaking changes.**

Framework-agnostic fluent PHP SDK for [Vatly](https://vatly.com) billing. Wraps the [vatly-api-php](https://github.com/Vatly/vatly-api-php) client with expressive, action-based methods for managing subscriptions, checkouts, customers, and webhooks.

This package serves as the core logic layer, designed to be portable across PHP frameworks and as a reference for future language ports (JS, Python).

## Installation

```bash
composer require vatly/vatly-fluent-php:v0.1.0-alpha.1
```

Pin to an exact version. This is an alpha release and the API will change.

## Requirements

- PHP 8.2+
- A Vatly API key ([vatly.com](https://vatly.com))

## What's included

- **Actions:** CreateCheckout, CreateCustomer, GetCheckout, GetCustomer, GetSubscription, GetPaymentMethodUpdateUrl, CancelSubscription, SwapSubscriptionPlan
- **Webhook handling:** Signature verification, event factory, typed event objects
- **Contracts:** BillableInterface, repository interfaces for framework integration
- **Responses:** Typed response objects for all actions

## Usage

```php
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCheckout;

$client = new VatlyApiClient();
$client->setApiKey('test_xxxxxxxxxxxx');

$checkout = new CreateCheckout($client);
$response = $checkout->execute([
    'products' => ['subscription_plan_id'],
    'customerId' => 'cust_xxx',
    'redirectUrlSuccess' => 'https://example.com/success',
    'redirectUrlCanceled' => 'https://example.com/canceled',
]);
```

## For framework integrations

If you're using Laravel, see [vatly/vatly-laravel](https://github.com/Vatly/vatly-laravel) which provides Eloquent models, traits, builders, and event listeners on top of this package.

## Testing

```bash
composer test
```

## License

MIT
