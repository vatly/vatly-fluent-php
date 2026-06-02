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
| Domain model (`Subscription`, `Order`, `CustomerService` helper) | — | ✓ |
| Repository contracts for persisting subscriptions / orders / customer bindings | — | ✓ |
| Webhook signature verification | ✓ (low-level) | ✓ (full pipeline incl. parsing, reactions, dispatch) |
| Typed domain events (`OrderPaid`, `SubscriptionStarted`, …) | — | ✓ |
| Builders for checkout / subscription flows | — | ✓ |
| Single composition root (`Vatly`) that wires everything from contracts | — | ✓ |

If you only need to fetch a customer or create a checkout from a script, `vatly-api-php` is enough. As soon as you want webhook handling, subscription state tracking, or anything resembling an integration, use fluent.

## Installation

Requires PHP 8.1+ and a Vatly API key.

```bash
composer require vatly/vatly-fluent-php:v0.8.0-alpha.1
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

Methods that need persistence or event dispatching (`customers()`, `webhookProcessor()`, `subscription()`, `order()`) throw `IncompleteWiringException` in api-only mode. Use the driver guide below if you need them.

---

## Step-by-step — building a framework driver

A driver is a thin glue package (e.g. `vatly-laravel`) that supplies fluent with concrete implementations of its contracts and exposes the API surface idiomatically for its framework.

### Webhook pipeline at a glance

For incoming Vatly webhooks, fluent dispatches a typed event and runs a built-in reaction that calls back into your repos. A driver author only needs to implement the repo methods — the wiring is fixed:

| Vatly event              | Dispatched event class                                            | Built-in reaction              | Repo method(s) called                                |
|--------------------------|-------------------------------------------------------------------|--------------------------------|------------------------------------------------------|
| `order.paid`             | `OrderPaid`                                                       | `StoreOrderOnPaid`             | `OrderWriter::store` (new) / `OrderWriter::update` (existing) |
| `order.canceled`         | `OrderCanceled`                                                   | `CancelOrderOnCanceled`        | `OrderWriter::update` (mirrors `canceled` status)    |
| `order.chargeback_received` | `OrderChargebackReceived`                                      | — (dispatched only)            | none — driver-handled                                |
| `order.chargeback_reversed` | `OrderChargebackReversed`                                      | — (dispatched only)            | none — driver-handled                                |
| `refund.completed` / `refund.failed` / `refund.canceled` | `RefundCompleted` / `RefundFailed` / `RefundCanceled` | `SyncRefundOnStatusChange` *(opt-in)* | `RefundWriter::store` (new) / `::update` (existing) |
| `subscription.started`   | `SubscriptionStarted`                                             | `SyncSubscriptionOnStarted`    | `SubscriptionWriter::store` (new) / `::update` (existing) |
| `subscription.billing_updated` | `SubscriptionBillingUpdated`                               | `SyncSubscriptionOnBillingUpdated` | `SubscriptionWriter::update` (refreshes mandate)    |
| `subscription.resumed`   | `SubscriptionResumed`                                             | `ResumeSubscriptionOnResumed`  | `SubscriptionWriter::update` (clears end date)       |
| `subscription.canceled`  | `SubscriptionCanceledImmediately` / `SubscriptionCanceledWithGracePeriod` | `CancelSubscriptionOnCanceled` | `SubscriptionWriter::update`                         |
| `subscription.cancellation_grace_period_completed` | `SubscriptionCancellationGracePeriodCompleted` | `EndSubscriptionOnGracePeriodCompleted` | `SubscriptionWriter::update` (stamps actual end date) |
| `checkout.paid` / `checkout.failed` / `checkout.canceled` / `checkout.expired` | `CheckoutPaid` / `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` | — (dispatched only) | none — driver-handled |

`OrderWriter::store`, `SubscriptionWriter::store`, and `RefundWriter::store` may return `null` if your driver can't route the data (see the adapter recipe below). Built-in reactions tolerate null — `SyncSubscriptionOnStarted` skips its follow-up `LocalSubscriptionCreated` dispatch when store returns null.

`subscription.billing_updated`, `subscription.resumed`, and `order.canceled` are find-or-skip: they update an existing local record but never create one. `subscription.billing_updated` re-fetches the subscription to keep the stored mandate (card last-4, masked IBAN) in step with the payment method on file; `subscription.resumed` clears the stored end date so a resume reactivates the derived state; `order.canceled` mirrors Vatly's `canceled` status onto the local order.

**Refunds** are opt-in: supply a `RefundRepositoryInterface` via `Wiring(refunds: …)` and the built-in `SyncRefundOnStatusChange` reaction persists `refund.*` webhooks (store-or-update, like orders) — unblocking terminal-state refund reconciliation. Omit it and the typed refund events are still dispatched for you to handle. Refund events are enriched via `GetRefund` so they carry the full tax breakdown, mirroring `order.paid`.

Read the refunds back idiomatically with `RefundReader::listForOrder` / `listForCustomer`, or via the handle: `$vatly->order($localOrder)->refunds()` returns the `RefundInterface[]` recorded against that order (local read, no API call; empty array when no refund repo is wired).

The order's reversal progress is read live from the Vatly API rather than synthesized into a local status — the order's own `status` stays terminal `paid`. `OrderHandle` exposes `reversedSubtotal()` / `refundableSubtotal()` (integer cents) and `isReversed()` / `isPartiallyReversed()` / `isFullyReversed()`, fetched once and memoized per handle instance. Because the API's `reversedSubtotal` combines refunds **and** chargebacks, these helpers answer "did money come back, and how much" regardless of how it was reversed.

**Chargebacks** ship no built-in reaction: Vatly's public order status doesn't change on a chargeback, so fluent doesn't synthesize one. Instead `OrderChargebackReceived` / `OrderChargebackReversed` are dispatched (with the affected order's ID as `orderId`) for your driver to react to — e.g. suspend access on receipt, reinstate on reversal.

**Checkout events** are dispatched only — no built-in reaction. The `checkout.*` deliveries carry the full Checkout resource (status, `customerId`, `orderId`, `metadata`) with no sparse money/tax fields, so they need no enriching API GET and are built straight from the payload. Use `CheckoutPaid` for an analytics/receipt handoff at the earliest "customer paid" moment — before `order.paid`'s tax-summary enrichment — and `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` for retry and cart-abandonment funnel hooks. `customerId` is nullable: an anonymous checkout only gets a customer attributed once payment completes.

**`subscription.cancellation_grace_period_completed`** stamps the actual end date onto the local row via `EndSubscriptionOnGracePeriodCompleted`. In the happy path the grace end was already stamped by `CancelSubscriptionOnCanceled` when the cancellation arrived, so this is an idempotent re-write — but it self-heals a missed or out-of-order cancellation webhook (which would otherwise leave `endsAt` null and the subscription looking active forever) and corrects any drift between the scheduled and actual end. The event is also dispatched so a driver can flip local state atomically instead of polling `endsAt < now` on a scheduled job; whether to additionally write a `fully_ended` status is driver-specific (Vatly has no such status to mirror), so that's left to the consumer.

`additionalWebhookReactions` (on `WebhookProcessorFactory::create`) lets you append driver-specific reactions without losing the built-ins.

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

### 2. Implement `CustomerBindingRepository`

The binding repository links a Vatly customer id (`cus_...`) to whatever id your app uses for its billing entity (User, Organization, Tenant, …). Fluent never touches your host model directly — it only asks "what is the Vatly customer id for this host customer id?" and the reverse.

```php
use Vatly\Fluent\Contracts\CustomerBindingRepository;

final class SymfonyCustomerBindingRepository implements CustomerBindingRepository
{
    public function __construct(private Connection $db) {}

    public function bind(string $vatlyCustomerId, string $hostCustomerId): void
    {
        $this->db->executeStatement(
            'UPDATE users SET vatly_id = ? WHERE id = ?',
            [$vatlyCustomerId, $hostCustomerId],
        );
    }

    public function record(string $vatlyCustomerId): void
    {
        // No-op: rows arrive with a null host customer id via the
        // anonymous-checkout flow and get attributed later. Override if
        // your driver tracks unattributed customers in a join table instead.
    }

    public function hostCustomerIdFor(string $vatlyCustomerId): ?string
    {
        return $this->db->fetchOne('SELECT id FROM users WHERE vatly_id = ?', [$vatlyCustomerId]) ?: null;
    }

    public function vatlyCustomerIdFor(string $hostCustomerId): ?string
    {
        return $this->db->fetchOne('SELECT vatly_id FROM users WHERE id = ?', [$hostCustomerId]) ?: null;
    }
}
```

<details>
<summary><strong>What if I can't add a <code>vatly_id</code> column?</strong> — vendor user model, third-party identity provider, multi-tenant…</summary>

Implement the same four methods against a dedicated join table. The host class itself stays untouched.

```sql
CREATE TABLE vatly_customer_bindings (
    host_customer_id  VARCHAR(255) NOT NULL,
    vatly_customer_id VARCHAR(255) NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (host_customer_id),
    UNIQUE      (vatly_customer_id)
);
```

`bind` `INSERT … ON CONFLICT … DO UPDATE`; `hostCustomerIdFor` / `vatlyCustomerIdFor` are single-column lookups; `record` is allowed to be a no-op (or insert a row with an empty host id if you want an audit trail for unattributed customers — `attribute()` can fill it in later).

**Multi-tenant fan-out.** If multiple host types (User, Organization, Tenant) should all participate as Vatly customers, add an `owner_type` column to the primary key and inject which type the repository handles at construction time — one repository instance per host type.

</details>

### 3. Implement your `SubscriptionInterface` model

State accessors + the derived predicates. Use `DerivesSubscriptionState` to get the six predicates for free.

```php
use DateTimeInterface;
use Vatly\Fluent\Concerns\DerivesSubscriptionState;
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

    // Mandate summary on file — persist these alongside the rest so portals
    // render "card ending in 4242" without a per-request API roundtrip.
    public function getMandateMethod(): ?string { /* 'card', 'sepa_debit', null, ... */ }
    public function getMandateMaskedIdentifier(): ?string { /* '4242', 'NL91****4300', null */ }
}
```

### 4. Implement your `OrderInterface` model

```php
use Vatly\Fluent\Contracts\OrderInterface;

class Order implements OrderInterface
{
    public function getVatlyId(): string { /* ... */ }
    public function getStatus(): string { /* ... */ }
    public function getInvoiceNumber(): ?string { /* ... */ }
    public function getTotal(): int { /* ... */ }
    public function getCurrency(): string { /* ... */ }
    public function getPaymentMethod(): ?string { /* ... */ }
    public function isPaid(): bool { return $this->status === 'paid'; }
}
```

<details>
<summary><strong>What if my host already has Order / Subscription tables?</strong> — bolting onto an ecosystem plugin (FluentCart, PMPro, MemberPress, EDD…)</summary>

This is the common case for ecosystem-plugin drivers: the host already models orders and subscriptions, and you can't (or shouldn't) add a parallel set. **Adapt, don't duplicate.** Write a thin wrapper that implements fluent's interface against the host's record:

```php
use Vatly\Fluent\Contracts\OrderInterface;

final class FluentCartOrder implements OrderInterface
{
    public function __construct(private OrderTransaction $txn) {}

    public function getVatlyId(): string       { return $this->txn->vatly_id; }
    public function getStatus(): string        { return $this->txn->status; }
    public function getInvoiceNumber(): ?string{ return $this->txn->invoice_no; }
    public function getTotal(): int            { return (int) $this->txn->total; }
    public function getCurrency(): string      { return $this->txn->currency; }
    public function getPaymentMethod(): ?string{ return $this->txn->payment_method; }
    public function isPaid(): bool             { return $this->txn->status === 'paid'; }
}
```

Your `OrderRepositoryInterface::store` then routes the incoming `StoreOrderData` to the right host record — typically by reading `$data->metadata` to find a host-side id the original checkout stamped onto the Vatly order. When the routing legitimately doesn't match (metadata is missing, host record was deleted, etc.), return `null`:

```php
public function store(StoreOrderData $data): ?OrderInterface
{
    $txnId = $data->metadata['fluentcart_transaction_id'] ?? null;
    if ($txnId === null) {
        return null; // anonymous / audit-only — nothing to attach to
    }

    $txn = OrderTransaction::find($txnId);
    if ($txn === null) {
        return null;
    }

    $txn->vatly_id = $data->vatlyId;
    $txn->save();

    return new FluentCartOrder($txn);
}
```

Same shape for `SubscriptionRepositoryInterface::store`. Built-in reactions tolerate null returns.

</details>

### 5. Implement the three repository contracts

Each entity-side contract has three methods. See [src/Contracts](src/Contracts) for signatures.

- `SubscriptionRepositoryInterface` — `findByVatlyId`, `store`, `update`
- `OrderRepositoryInterface` — `findByVatlyId`, `store`, `update`
- `RefundRepositoryInterface` — `findByVatlyId`, `listForOrder`, `listForCustomer`, `store`, `update` (**optional** — only needed to persist `refund.*` webhooks)
- `WebhookCallRepositoryInterface` — record received webhook calls (audit log)

`StoreSubscriptionData` and `StoreOrderData` both carry an optional `hostCustomerId` resolved from the binding repo when fluent persists from a webhook reaction. Use it to fill your host-side owner column when it's set, and accept `null` for the anonymous-checkout flow.

```php
public function store(StoreSubscriptionData $data): SubscriptionInterface
{
    $attrs = [
        'vatly_id' => $data->vatlyId,
        'type'     => $data->type,
        'plan_id'  => $data->planId,
        'name'     => $data->name,
        'quantity' => $data->quantity,
    ];

    if ($data->hostCustomerId !== null) {
        $attrs['owner_id'] = $data->hostCustomerId;
    }

    return Subscription::create($attrs);
}
```

> Each entity-side repo is also exposed as a Reader / Writer pair (`SubscriptionReader` + `SubscriptionWriter`, etc.). The combined interface extends both. Typehint the narrowest role you actually need.

> **If your repo needs to call back into the SDK** — e.g. `GetOrder` to read fresh metadata from a partial webhook payload — don't inject `Vatly` directly. `Vatly` is being constructed *with* your repo, so a direct dependency is circular. Instead, inject a lazy resolver (your host's container, a singleton accessor, or a closure that returns `Vatly`) and resolve at call time.

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
    config:           $config,                  // your ConfigurationInterface impl
    subscriptions:    $subscriptionsRepo,
    orders:           $ordersRepo,
    webhookCalls:     $webhookCallsRepo,
    events:           $eventDispatcher,
    customerBindings: $customerBindingsRepo,
));
```

Register the `Vatly` instance as a singleton in your framework's container. Everything else resolves through it.

If your driver ships plugin-specific webhook reactions (e.g. assigning a membership level on `subscription.started`), pass them as `additionalWebhookReactions`:

```php
$vatly = new Vatly(new Wiring(
    config:           $config,
    // ... repos + events + bindings ...
    additionalWebhookReactions: [
        new AssignMembershipLevelOnStarted(...),
        new RevokeMembershipLevelOnCanceled(...),
    ],
));
```

They run after fluent's built-in reactions (subscription sync, order persistence, cancellation handling).

### 8. Use the SDK — two paths

**Action-driven.** For drivers that want consumers to reach the SDK explicitly. Reach `Vatly` through your container:

```php
$vatly = $container->get(Vatly::class);

// Create a checkout — pass in a CustomerProfile carrying whatever the host knows.
use Vatly\Fluent\CustomerProfile;

$checkout = $vatly
    ->checkoutBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->withRedirectUrlSuccess('https://app.example.com/done')
    ->withRedirectUrlCanceled('https://app.example.com/oops')
    ->create([['id' => 'plan_premium', 'quantity' => 1]], '...', '...');

// Subscribe
$checkout = $vatly
    ->subscriptionBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->toPlan('plan_premium')
    ->create();

// Subscribe with a free trial. withTrialDays() is the whole-day form;
// withTrialEndsAt() takes a DateTimeInterface and rounds up to whole days
// (Vatly's trial input is day-granular) so the trial never ends early.
$checkout = $vatly
    ->subscriptionBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->toPlan('plan_premium')
    ->withTrialDays(14)
    ->create();

// Operate on a stored Subscription / Order
$vatly->subscription($localSubscription)->cancel();
$vatly->order($localOrder)->invoiceUrl();

// Billing address / VAT / company name changes go through a hosted Vatly
// flow. Returns a fresh redirect URL per call — don't cache.
// `redirectUrlSuccess` and `redirectUrlCanceled` are filled in from the
// config defaults when omitted; pass them in $prefillData to override.
$url = $vatly->subscription($localSubscription)->updateBilling();

// Optionally prefill the billing address:
$url = $vatly->subscription($localSubscription)->updateBilling([
    'billingAddress' => [
        'streetAndNumber' => 'Damrak 1',
        'city'            => 'Amsterdam',
        'country'         => 'NL',
    ],
]);
```

**Customer helper.** For host-first flows where you create a Vatly customer for a known host entity and want the link recorded automatically:

```php
$customer = $vatly->customers()->createFor(
    hostCustomerId: (string) $user->id,
    profile:        new CustomerProfile(email: $user->email, name: $user->name),
);
// $customer->id is now bound to $user->id via your CustomerBindingRepository.

// Forward extra create-customer API keys via $additionalPayload (locale,
// metadata, or anything else the create-customer endpoint accepts).
$customer = $vatly->customers()->createFor(
    hostCustomerId:    (string) $user->id,
    profile:           new CustomerProfile(email: $user->email, name: $user->name),
    additionalPayload: ['locale' => 'nl_NL', 'metadata' => ['internal_id' => $user->id]],
);

// Look up later
$existing = $vatly->customers()->findByHostCustomerId((string) $user->id);
```

Drivers commonly wrap these calls in idiomatic shortcuts — e.g. a Laravel trait that adds `$user->subscribe()->toPlan(...)->create()` on top of `subscriptionBuilder($user->customerProfile())`.

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

The processor handles signature verification, parses the payload into typed events, runs reactions that persist state via your repos (consulting the binding repo to fill `hostCustomerId` on stored rows), and dispatches domain events on your event bus.

### 10. (Optional) Expose operations on your local models

For Cashier-style ergonomics, give your Eloquent / Doctrine entities operation methods that delegate to fluent:

```php
class Subscription implements SubscriptionInterface
{
    // ... interface state accessors (getVatlyId, getMandateMethod, etc.) ...

    public function cancel(): void
    {
        $container->get(Vatly::class)->subscription($this)->cancel();
    }

    public function swap(string $planId): self
    {
        $container->get(Vatly::class)->subscription($this)->swap($planId);
        return $this;
    }

    public function billingUpdateUrl(array $prefillData = []): string
    {
        return $container->get(Vatly::class)
            ->subscription($this)
            ->updateBilling($prefillData);
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

// Composed services — require repos in Wiring
$vatly->customers();                               // CustomerService (lazy, cached)
$vatly->checkoutBuilder($profile);                 // CheckoutBuilder (per-call)
$vatly->subscriptionBuilder($profile);             // SubscriptionBuilder (per-call)
$vatly->subscription($localSubscription);          // SubscriptionHandle wrapping local state
$vatly->order($localOrder);                        // OrderHandle wrapping local state
$vatly->webhookProcessor();                        // WebhookProcessor (also needs events dispatcher)
```

Calling a composed-services method without the required repos in `Wiring` throws `IncompleteWiringException` with a message naming what's missing.

## Contracts at a glance

In [src/Contracts](src/Contracts):

- `SubscriptionInterface` — local subscription state + derived predicates
- `OrderInterface` — local order state
- `CustomerBindingRepository` — bidirectional mapping between Vatly customer ids and host ids
- `SubscriptionRepositoryInterface` — subscription persistence (3 methods). Splits into `SubscriptionReader` (find) + `SubscriptionWriter` (store/update).
- `OrderRepositoryInterface` — order persistence (3 methods). Splits into `OrderReader` (find) + `OrderWriter` (store/update).
- `RefundRepositoryInterface` — refund persistence (optional). Splits into `RefundReader` (find + `listForOrder` / `listForCustomer`) + `RefundWriter` (store/update).
- `WebhookCallRepositoryInterface` — webhook audit log (write-only by nature)
- `EventDispatcherInterface` — fire domain events
- `ConfigurationInterface` — API key, URL, version, webhook secret, redirect defaults
- `WebhookReactionInterface` — extension point for adding your own webhook reactions. Compose multiple via `Webhooks\Reactions\WebhookReactionChain` (variadic constructor; the chain itself implements the same interface).

## Domain events

Dispatched by webhook reactions through your `EventDispatcherInterface`. Subscribe to them in your framework's event bus.

- `WebhookReceived` — raw webhook envelope (typed shape; `object` is the resource payload)
- `OrderPaid` — order with full `taxSummary` breakdown, ready to materialize local invoices
- `OrderCanceled`
- `OrderChargebackReceived` / `OrderChargebackReversed` — dispute signals carrying the affected `orderId`
- `RefundCompleted` / `RefundFailed` / `RefundCanceled` — each with full `taxSummary` breakdown
- `SubscriptionStarted`
- `SubscriptionBillingUpdated` — billing/mandate changed; carries the refreshed mandate summary
- `SubscriptionResumed`
- `SubscriptionCanceledImmediately`
- `SubscriptionCanceledWithGracePeriod`
- `LocalSubscriptionCreated`
- `UnsupportedWebhookReceived`

## Testing

```bash
composer test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
