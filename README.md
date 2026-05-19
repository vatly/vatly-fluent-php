# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)

> **Alpha release -- under active development. Expect breaking changes.**

Framework-agnostic fluent PHP SDK for [Vatly](https://vatly.com) billing. Wraps the [vatly-api-php](https://github.com/Vatly/vatly-api-php) client with expressive, action-based methods for managing subscriptions, checkouts, customers, and webhooks.

This package serves as the core logic layer, designed to be portable across PHP frameworks and as a reference for future language ports (JS, Python).

## Installation

```bash
composer require vatly/vatly-fluent-php
```

This package follows semantic versioning. During alpha, pin to exact versions if you need stability:

```bash
composer require vatly/vatly-fluent-php:v0.4.0-alpha.1
```

## Requirements

- PHP 8.0+
- A Vatly API key ([vatly.com](https://vatly.com))

## What's included

- **`Vatly` facade:** single entry point that lazy-instantiates actions and exposes the API client, signature verifier, and webhook event factory
- **Actions:** CreateCheckout, CreateCustomer, GetCheckout, GetCustomer, GetSubscription, CreateSubscriptionBillingUpdateLink, CancelSubscription, SwapSubscriptionPlan
- **Builders:** framework-agnostic `CheckoutBuilder` and `SubscriptionBuilder` driven by a `BillableInterface`
- **Webhook handling:** signature verification, event factory, typed event objects (`OrderPaid`, `SubscriptionStarted`, `SubscriptionCanceledImmediately`, `SubscriptionCanceledWithGracePeriod`, etc.), and a `WebhookProcessor` that dispatches built-in reactions (sync subscription, store order, cancel subscription) plus your own
- **Contracts:** `BillableInterface`, repository interfaces (`SubscriptionRepositoryInterface`, `OrderRepositoryInterface`, `CustomerRepositoryInterface`, `WebhookCallRepositoryInterface`), `EventDispatcherInterface`, `ConfigurationInterface` for framework integration
- **Data DTOs:** immutable inputs for repository store/update operations (`StoreOrderData`, `StoreSubscriptionData`, `UpdateOrderData`, `UpdateSubscriptionData`)

Actions return raw `Vatly\API\Resources\*` objects from the underlying [vatly-api-php](https://github.com/Vatly/vatly-api-php) client — there is no separate response wrapper layer.

## Usage

```php
use Vatly\Fluent\Vatly;

$vatly = new Vatly('test_xxxxxxxxxxxx');

$checkout = $vatly->createCheckout()->execute([
    'products' => [
        ['id' => 'subscription_plan_id', 'quantity' => 1],
    ],
    'customerId' => 'cust_xxx',
    'redirectUrlSuccess' => 'https://example.com/success',
    'redirectUrlCanceled' => 'https://example.com/canceled',
]);
```

`$checkout` is a `Vatly\API\Resources\Checkout` — see [vatly-api-php](https://github.com/Vatly/vatly-api-php) for the full resource shape.

## For framework integrations

If you're using Laravel, see [vatly/vatly-laravel](https://github.com/Vatly/vatly-laravel). It provides Eloquent models, a `Billable` trait, Eloquent-aware repositories (implementations of the contracts above), Laravel-flavoured builders (driven by `Illuminate\Database\Eloquent\Model` and `Collection`), an event-bus bridge, and a webhook controller — all on top of this package.

## Testing

```bash
composer test
```

## License

MIT
