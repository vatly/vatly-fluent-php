# Vatly Fluent PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)
[![Tests](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-fluent-php/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-fluent-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-fluent-php)

> **Alpha — under active development. Expect breaking changes between minor versions.**

Framework-agnostic SDK for [Vatly](https://vatly.com). Sits between `vatly/vatly-api-php` (the raw HTTP client) and a framework driver (e.g. `vatly/vatly-laravel`).

## Pick the right package

| Use case | Package |
| --- | --- |
| Laravel app | [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) — already wires fluent for you |
| Building a driver for another framework (Symfony, Yii, …) | **`vatly/vatly-fluent-php`** (this) |
| Standalone PHP script / CLI tool | **`vatly/vatly-fluent-php`** (this, api-only mode) |
| Just need raw HTTP requests, no domain model | [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) |

## Fluent vs `vatly-api-php`

`vatly-api-php` is the wire layer: turn method calls into HTTPS requests, parse responses into typed resources. Fluent is the layer above:

| Concern | `vatly-api-php` | `vatly-fluent-php` |
| --- | --- | --- |
| Make API calls | ✓ | ✓ (via `vatly-api-php` underneath) |
| Domain model (`Billable`, `SubscriptionHandle`, `OrderHandle`) | — | ✓ |
| Repository contracts for persisting subscriptions / orders / customers | — | ✓ |
| Webhook signature verification | ✓ (low-level) | ✓ (full pipeline incl. parsing, reactions, dispatch) |
| Typed domain events (`OrderPaid`, `SubscriptionStarted`, …) | — | ✓ |
| Builders for checkout / subscription flows | — | ✓ |
| Single composition root (`Vatly`) that wires everything from contracts | — | ✓ |
| Cashier-style operations (`->subscribe()`, `->cancel()`, …) | — | ✓ |

If you only need to fetch a customer or create a checkout from a script, `vatly-api-php` is enough. As soon as you want webhook handling, subscription state tracking, or anything resembling an integration, use fluent.

## Installation

Requires PHP 8.2+ and a Vatly API key.

```bash
composer require vatly/vatly-fluent-php:v0.7.0-alpha.1
```

Pin to an exact version during alpha.

---

## Quick start — standalone / api-only

For one-off scripts that just hit the API. No persistence, no webhook processing, no event dispatching.

1. **Build a `Vatly` with just an API key:**

   ```php
   use Vatly\Fluent\Vatly;

   $vatly = Vatly::apiOnly('test_xxxxxxxxxxxxxxxxxx');
   ```

2. **Call any action accessor:**

   ```php
   $customer = $vatly->createCustomer()->execute([
       'email' => 'sander@example.com',
       'name'  => 'Sander',
   ]);

   $order = $vatly->getOrder()->execute('order_abc123');
   $sub   = $vatly->getSubscription()->execute('sub_xyz789');
   ```

   Available accessors: `createCustomer`, `getCustomer`, `getOrder`, `createCheckout`, `getSubscription`, `cancelSubscription`, `resumeSubscription`, `swapSubscriptionPlan`, `updateSubscriptionBilling`.

3. **Override the API endpoint** (e.g. for sandbox / proxy):

   ```php
   use Vatly\Fluent\Configuration\ArrayConfiguration;
   use Vatly\Fluent\Vatly;
   use Vatly\Fluent\Wiring;

   $vatly = new Vatly(new Wiring(
       config: new ArrayConfiguration([
           'api_key'     => 'test_xxxxxxxxxxxxxxxxxx',
           'api_url'     => 'https://api.sandbox.vatly.com',
           'api_version' => 'v1',
       ]),
   ));
   ```

Methods that need persistence or event dispatching (`billableFactory()`, `webhookProcessor()`, `subscriptionHandle()`, `orderHandle()`) throw `IncompleteWiring` in api-only mode. Use the driver guide below if you need them.

---

## Step-by-step — building a framework driver

A driver is a thin glue package (e.g. `vatly-laravel`) that supplies fluent with concrete implementations of its contracts and exposes the API surface idiomatically for its framework. Steps:

### 1. Implement `ConfigurationInterface`

Read your framework's config and return values.

```php
namespace Acme\VatlySymfony;

use Vatly\Fluent\Concerns\DerivesTestmodeFromApiKey;
use Vatly\Fluent\Contracts\ConfigurationInterface;

final class SymfonyVatlyConfig implements ConfigurationInterface
{
    use DerivesTestmodeFromApiKey;  // free isTestmode() from key prefix

    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.vatly.com',
        private string $apiVersion = 'v1',
        private ?string $webhookSecret = null,
        private string $successUrl = '',
        private string $canceledUrl = '',
    ) {}

    public function getApiKey(): string { return $this->apiKey; }
    public function getApiUrl(): string { return $this->apiUrl; }
    public function getApiVersion(): string { return $this->apiVersion; }
    public function getWebhookSecret(): ?string { return $this->webhookSecret; }
    public function getDefaultRedirectUrlSuccess(): string { return $this->successUrl; }
    public function getDefaultRedirectUrlCanceled(): string { return $this->canceledUrl; }
}
```

### 2. Implement `BillableInterface` on your owner model

The "owner" is whatever your app treats as the billing customer (User, Organization, Tenant, …).

```php
use Vatly\Fluent\Contracts\BillableInterface;

class User implements BillableInterface
{
    public function getVatlyId(): ?string { return $this->vatly_id; }
    public function setVatlyId(string $id): void { $this->vatly_id = $id; }
    public function hasVatlyId(): bool { return $this->vatly_id !== null; }
    public function getVatlyEmail(): ?string { return $this->email; }
    public function getVatlyName(): ?string { return $this->name; }
}
```

> Can't modify the owner class? See [docs/recipes/cannot-customize-owner-model.md](docs/recipes/cannot-customize-owner-model.md) for the adapter pattern + two storage options.

### 3. Implement your `SubscriptionInterface` model

State accessors + the derived predicates. Use `DerivesSubscriptionState` to get the six predicates for free.

```php
use DateTimeInterface;
use Vatly\Fluent\Concerns\DerivesSubscriptionState;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;

class Subscription implements SubscriptionInterface
{
    use DerivesSubscriptionState;  // isActive, isCancelled, isOnGracePeriod,
                                   // isValid, isRecurring, isEnded

    public function getVatlyId(): string { /* ... */ }
    public function getType(): string { /* ... */ }
    public function getPlanId(): string { /* ... */ }
    public function getName(): string { /* ... */ }
    public function getQuantity(): int { /* ... */ }
    public function getEndsAt(): ?DateTimeInterface { /* ... */ }
    public function getOwner(): BillableInterface { /* ... */ }
}
```

### 4. Implement your `OrderInterface` model

```php
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\OrderInterface;

class Order implements OrderInterface
{
    public function getVatlyId(): string { /* ... */ }
    public function getStatus(): string { /* ... */ }
    public function getInvoiceNumber(): ?string { /* ... */ }
    public function getTotal(): int { /* ... */ }
    public function getCurrency(): string { /* ... */ }
    public function getPaymentMethod(): ?string { /* ... */ }
    public function getOwner(): BillableInterface { /* ... */ }
    public function isPaid(): bool { return $this->status === 'paid'; }
}
```

### 5. Implement the four repository contracts

Each contract is small (3–5 methods). See [src/Contracts](src/Contracts) for signatures.

- `CustomerRepositoryInterface` — find/save the billable owner by Vatly id
- `SubscriptionRepositoryInterface` — find / store / update local subscriptions
- `OrderRepositoryInterface` — find / store / update local orders
- `WebhookCallRepositoryInterface` — record received webhook calls (audit log)

Use your framework's ORM. There's no fluent-side abstraction over query building — you write straight ORM code.

### 6. Implement `EventDispatcherInterface`

```php
use Vatly\Fluent\Contracts\EventDispatcherInterface;

class SymfonyEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $bus) {}

    public function dispatch(object $event): void
    {
        $this->bus->dispatch($event);
    }
}
```

If you don't need events, use `Vatly\Fluent\Events\NullEventDispatcher` (no-op).

### 7. Construct `Vatly` from a `Wiring`

```php
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

$vatly = new Vatly(new Wiring(
    config:        $config,            // your ConfigurationInterface impl
    subscriptions: $subscriptionsRepo,
    customers:     $customersRepo,
    orders:        $ordersRepo,
    webhookCalls:  $webhookCallsRepo,
    events:        $eventDispatcher,
));
```

Register the `Vatly` instance as a singleton in your framework's container. Everything else resolves through it.

### 8. Expose the per-owner orchestrator

Give your User model a way to reach `Vatly\Fluent\Billable`:

```php
class User implements BillableInterface
{
    // ...

    public function billable(): \Vatly\Fluent\Billable
    {
        return $container->get(Vatly::class)->billable($this);
    }
}
```

Now consumers write:

```php
$checkout = $user->billable()->subscribe()->toPlan('plan_premium')->create();
$user->billable()->subscription('default')?->cancel();
$user->billable()->order('order_abc')->invoiceUrl();
```

You can add idiomatic shortcuts on the User class (e.g. `$user->subscribe()` proxying to `billable()->subscribe()`). The Laravel driver does this via a trait — see [`vatly-laravel`'s Billable trait](https://github.com/Vatly/vatly-laravel/blob/main/src/Billable.php) for reference.

### 9. Wire the webhook receiver

Vatly POSTs webhooks to a URL of your choice. Your driver:

1. Defines an HTTP route in your framework.
2. The route handler reads the raw request body and the `Vatly-Signature` header.
3. Passes both into `$vatly->webhookProcessor()->handle($payload, $signature)`.
4. Returns `201` on success, `403` on `InvalidWebhookSignatureException`.

Example handler:

```php
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Webhooks\SignatureVerifier;

public function handle(SomeRequest $request)
{
    try {
        $this->vatly->webhookProcessor()->handle(
            payload:   $request->getRawBody(),
            signature: $request->headers->get(SignatureVerifier::SIGNATURE_HEADER_NAME, ''),
        );
    } catch (InvalidWebhookSignatureException) {
        return new Response(status: 403);
    }

    return new Response(status: 201);
}
```

The processor handles signature verification, parses the payload into typed events, runs reactions that persist state via your repos, and dispatches domain events on your event bus.

### 10. (Optional) Expose handles on your local models

For Cashier-style ergonomics, give your Eloquent / Doctrine entities operation methods that delegate to handles:

```php
class Subscription implements SubscriptionInterface
{
    // ... interface methods ...

    public function cancel(): void
    {
        $container->get(Vatly::class)->subscriptionHandle($this)->cancel();
    }

    public function swap(string $planId): self
    {
        $container->get(Vatly::class)->subscriptionHandle($this)->swap($planId);
        return $this;
    }
}
```

This makes `foreach ($user->subscriptions as $sub) $sub->cancel()` work naturally.

---

## What `Vatly` (the composition root) exposes

```php
$vatly->getApiClient();                            // raw VatlyApiClient
$vatly->getSignatureVerifier();                    // raw webhook signature verifier
$vatly->getWebhookEventFactory();                  // parses webhook payloads

// Actions (lazy, cached)
$vatly->createCustomer();    $vatly->getCustomer();
$vatly->getOrder();          $vatly->createCheckout();
$vatly->getSubscription();   $vatly->cancelSubscription();
$vatly->resumeSubscription(); $vatly->swapSubscriptionPlan();
$vatly->updateSubscriptionBilling();

// Composed services (lazy, cached) — require repos in Wiring
$vatly->billableFactory();                         // BillableFactory
$vatly->billable($owner);                          // Billable bound to one owner
$vatly->subscriptionHandle($subscription);         // SubscriptionHandle wrapping local state
$vatly->orderHandle($order);                       // OrderHandle wrapping local state
$vatly->webhookProcessor();                        // WebhookProcessor (also needs events dispatcher)
```

Calling a composed-services method without the required repos in `Wiring` throws `IncompleteWiring` with a message naming what's missing.

## Contracts at a glance

In [src/Contracts](src/Contracts):

- `BillableInterface` — the owner of subscriptions/orders
- `SubscriptionInterface` — local subscription state + derived predicates
- `OrderInterface` — local order state
- `CustomerRepositoryInterface` — owner persistence
- `SubscriptionRepositoryInterface` — subscription persistence
- `OrderRepositoryInterface` — order persistence
- `WebhookCallRepositoryInterface` — webhook audit log
- `EventDispatcherInterface` — fire domain events
- `ConfigurationInterface` — API key, URL, version, webhook secret, redirect defaults
- `WebhookReactionInterface` — extension point for adding your own webhook reactions

## Domain events

Dispatched by webhook reactions through your `EventDispatcherInterface`. Subscribe to them in your framework's event bus.

- `WebhookReceived` — raw webhook envelope (typed shape; `object` is the resource payload)
- `OrderPaid` — order with full `taxSummary` breakdown, ready to materialize local invoices
- `SubscriptionStarted`
- `SubscriptionCanceledImmediately`
- `SubscriptionCanceledWithGracePeriod`
- `LocalSubscriptionCreated`
- `UnsupportedWebhookReceived`

## Recipes

For situational guides (sealed owner models, multi-tenant setups, …) see [docs/recipes/](docs/recipes/).

## Testing

```bash
composer test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
