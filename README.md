# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)

> **Alpha release -- under active development. Expect breaking changes.**

The framework-agnostic core that powers [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) — webhook processing, contracts, events, DTOs, and the per-owner orchestrator (`Vatly\Fluent\Billable`) that the Laravel package reuses so it doesn't reimplement them inline.

## Looking for a Vatly integration?

**Most users want vatly-laravel, not this package directly:**

| Use case | Package |
| --- | --- |
| Laravel application | [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) |
| Raw API access (no framework) | [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) |

This package on its own doesn't persist anything, dispatch events, or read configuration — those concerns belong to the Laravel package. You install it transitively when you require `vatly/vatly-laravel`.

## Installation

Requires PHP 8.0+ and a Vatly API key ([vatly.com](https://vatly.com)).

```bash
composer require vatly/vatly-fluent-php
```

Pin to an exact version during alpha:

```bash
composer require vatly/vatly-fluent-php:v0.5.0-alpha.1
```

## What's inside

- **Orchestrator** ([src/Billable.php](src/Billable.php), [src/BillableFactory.php](src/BillableFactory.php), [src/SubscriptionHandle.php](src/SubscriptionHandle.php)): the canonical per-owner API surface — `subscribe()`, `checkout()`, `subscribed()`, `subscription()`, `createAsVatlyCustomer()`, etc.
- **Contracts** ([src/Contracts](src/Contracts)): `BillableInterface`, `SubscriptionInterface`, `OrderInterface`, repository interfaces (subscription / customer / order / webhook call), `EventDispatcherInterface`, `ConfigurationInterface`, `WebhookReactionInterface`.
- **Webhook pipeline** ([src/Webhooks](src/Webhooks)): `WebhookProcessor` orchestrates signature verification → event parsing → audit logging → reactions → dispatch. Built-in reactions: `SyncSubscriptionOnStarted`, `StoreOrderOnPaid`, `CancelSubscriptionOnCanceled`. Wire it in one call with `WebhookProcessorFactory::create()`.
- **Events** ([src/Events](src/Events)): typed POPOs — `OrderPaid`, `SubscriptionStarted`, `SubscriptionCanceledImmediately`, `SubscriptionCanceledWithGracePeriod`, `LocalSubscriptionCreated`, `WebhookReceived`, `UnsupportedWebhookReceived`.
- **Actions** ([src/Actions](src/Actions)): thin wrappers around [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) returning raw `Vatly\API\Resources\*` objects.
- **Builders** ([src/Builders](src/Builders)): `CheckoutBuilder` and `SubscriptionBuilder` driven by a `BillableInterface`.
- **Data DTOs** ([src/Data](src/Data)): immutable inputs for repository operations.

## Testing

```bash
composer test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, design principles, and the PR process.

## License

MIT
