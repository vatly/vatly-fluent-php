# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)

> **Alpha release -- under active development. Expect breaking changes.**

Shared internals for [Vatly](https://vatly.com) framework drivers. This package is the framework-agnostic core that powers driver packages like [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) — webhook processing, contracts, events, and DTOs that drivers reuse so they don't reimplement the same patterns.

## Looking for a Vatly integration?

**Most users want a framework driver, not this package directly:**

| Framework | Package |
| --- | --- |
| Laravel | [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) |
| Symfony, WordPress, etc. | _Planned. Want to build one? See [CONTRIBUTING.md](CONTRIBUTING.md)._ |
| No framework / raw API | [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) |

This package on its own doesn't persist anything, dispatch events, or read configuration — those concerns belong to a driver. You install it transitively through a driver.

## What's inside

Drivers wire these pieces into their framework's IoC container, ORM, event bus, and routing:

- **Contracts** ([src/Contracts](src/Contracts)): `BillableInterface`, `SubscriptionInterface`, `OrderInterface`, repository interfaces (`SubscriptionRepositoryInterface`, `CustomerRepositoryInterface`, `OrderRepositoryInterface`, `WebhookCallRepositoryInterface`), `EventDispatcherInterface`, `ConfigurationInterface`, `WebhookReactionInterface`.
- **Webhook pipeline** ([src/Webhooks](src/Webhooks)): `WebhookProcessor` orchestrates signature verification → event parsing → audit logging → reactions → dispatch. Built-in reactions: `SyncSubscriptionOnStarted`, `StoreOrderOnPaid`, `CancelSubscriptionOnCanceled`.
- **Events** ([src/Events](src/Events)): typed POPOs — `OrderPaid`, `SubscriptionStarted`, `SubscriptionCanceledImmediately`, `SubscriptionCanceledWithGracePeriod`, `LocalSubscriptionCreated`, `WebhookReceived`, `UnsupportedWebhookReceived`.
- **Actions** ([src/Actions](src/Actions)): thin wrappers around [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) — `CreateCheckout`, `CreateCustomer`, `GetCheckout`, `GetCustomer`, `GetSubscription`, `CreateSubscriptionBillingUpdateLink`, `CancelSubscription`, `SwapSubscriptionPlan`. Return raw `Vatly\API\Resources\*` objects.
- **Builders** ([src/Builders](src/Builders)): `CheckoutBuilder` and `SubscriptionBuilder` driven by a `BillableInterface`.
- **Data DTOs** ([src/Data](src/Data)): immutable inputs for repository operations — `StoreOrderData`, `StoreSubscriptionData`, `UpdateOrderData`, `UpdateSubscriptionData`.

## Direct usage (advanced)

If you're not using a driver — for example a custom framework integration — you can wire this package up yourself. Start with the [Driver Author Guide](CONTRIBUTING.md), then use the `Vatly` entry point for read-only operations:

```php
use Vatly\Fluent\Vatly;

$vatly = new Vatly('test_xxxxxxxxxxxx');

$checkout = $vatly->createCheckout()->execute([
    'products' => [['id' => 'plan_abc', 'quantity' => 1]],
    'customerId' => 'cust_xxx',
    'redirectUrlSuccess' => 'https://example.com/success',
    'redirectUrlCanceled' => 'https://example.com/canceled',
]);
```

For webhook handling, subscription sync, and anything stateful, you'll need to implement the repository contracts and wire a `WebhookProcessor` — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Requirements

- PHP 8.0+
- A Vatly API key ([vatly.com](https://vatly.com))

## Installation

```bash
composer require vatly/vatly-fluent-php
```

Pin to an exact version during alpha:

```bash
composer require vatly/vatly-fluent-php:v0.4.0-alpha.3
```

## Testing

```bash
composer test
```

## Contributing & building a driver

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contracts a driver implements, the wiring pattern, and the planned consolidation work.

## License

MIT
