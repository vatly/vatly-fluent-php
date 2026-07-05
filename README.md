![Vatly Fluent PHP](art/banner.png)

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

   Available accessors: `createCustomer`, `getCustomer`, `updateCustomer`, `getOrder`, `createCheckout`, `getSubscription`, `cancelSubscription`, `resumeSubscription`, `swapSubscriptionPlan`, `updateSubscriptionBilling`.

   Admin/CRUD resources that fluent doesn't wrap (test helpers, webhook events, **webhook endpoints**, and the **subscription-plan / one-off-product** catalog) are reachable on the raw client via `$vatly->getApiClient()`:

   ```php
   // Register the delivery endpoint from code / IaC (at most one per mode).
   // The signing secret is write-only — keep the value you send; it's never returned.
   $endpoint = $vatly->getApiClient()->webhookEndpoints->create([
       'url'    => 'https://merchant.example/webhooks/vatly',
       'secret' => getenv('VATLY_WEBHOOK_SECRET'), // min 10 chars
   ]);

   // Create catalog products/plans from code (api-php ≥ 0.1.0-alpha.24).
   // A live_ token creates them in `pending` (await Vatly approval); a test_
   // token auto-approves to `active`. Read the price via $plan->basePrice->value.
   $plan = $vatly->getApiClient()->subscriptionPlans->create([
       'name'          => 'Pro Monthly',
       'description'   => 'Full access to all Pro features, billed monthly',
       'basePrice'     => ['value' => '29.00', 'currency' => 'EUR'],
       'productType'   => 'saas',
       'interval'      => 'month',
       'intervalCount' => 1,
   ]);
   ```

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

For incoming Vatly webhooks, fluent dispatches a typed event and runs a built-in reaction that calls back into your repos. A driver author only needs to implement the repo methods — the wiring is fixed. For the full event → reaction → repo-method matrix **including the fields each `Store*Data` / event carries**, see [docs/webhook-flow.md](docs/webhook-flow.md).

> **No API round-trip.** Vatly sends **fat, HMAC-signed** webhook deliveries: the payload's `object` is the full resource — byte-identical to the corresponding `GET /…/{id}` body (subtotal, the complete tax summary, lines, mandate). The HMAC signature is the trust boundary, so fluent builds every typed event straight from the signed payload — the money/tax-bearing events by hydrating the matching api-php Resource in memory, the rest from the envelope. There is **no follow-up `GET` to "enrich"**, so a transient API blip can never block a webhook.

> The typed webhook event DTOs (`OrderPaid`, `OrderPaymentFailed`, `SubscriptionStarted`, …) live in **`vatly-api-php`** under the `Vatly\API\Webhooks\Events\*` namespace and are consumed by fluent — fluent no longer ships its own copies. Import them from `Vatly\API\Webhooks\Events\…` when you handle dispatched events. The factory that turns a signed payload into those typed events, `Vatly\API\Webhooks\WebhookEventFactory`, also lives in **`vatly-api-php`** (verify → parse → map). Fluent consumes it and owns the downstream orchestration (the reactions and `WebhookProcessor`, which record → react → dispatch) plus the driver-side `Vatly\Fluent\Events\{SubscriptionWasCreatedFromWebhook,OrderWasCreatedFromWebhook,NullEventDispatcher}`.

> **Event money is `Money`, not int cents (api-php ≥ `0.1.0-alpha.18`):** the `total` / `subtotal` fields on the money-bearing events are `Vatly\API\Types\Money` value objects (decimal-string `value` + `currency`). Read the currency with `$event->total->currency` and flatten to integer cents with `$event->total->toCents()`. The standalone `currency` field was **removed** from `OrderPaid`, `OrderPaymentFailed`, and the `Refund*` events (their `total` / `subtotal` are non-null `Money`); the chargeback events (`OrderChargebackReceived` / `OrderChargebackReversed`) instead carry **nullable** `?Money` and **keep** a standalone `currency` string. Order lines moved to `Vatly\API\Types\OrderLineData[]` (from the old `Vatly\API\Data\`), and a line's `basePrice` / `total` / `subtotal` are `Money` too. Fluent's built-in reactions flatten `Money → int` at the persistence edge, so the `Store*Data` / `Update*Data` DTOs your driver implements against still receive integer-cents `total` / `subtotal` and a `currency` string — no driver change required.

| Vatly event              | Dispatched event class                                            | Built-in reaction              | Repo method(s) called                                |
|--------------------------|-------------------------------------------------------------------|--------------------------------|------------------------------------------------------|
| `order.paid`             | `OrderPaid`                                                       | `StoreOrderOnPaid`             | `OrderWriter::store` (new, then dispatches `OrderWasCreatedFromWebhook`) / `OrderWriter::update` (existing) |
| `order.payment_failed`   | `OrderPaymentFailed`                                              | `StoreOrderOnPaymentFailed`    | `OrderWriter::store` (new) / `OrderWriter::update` (existing); mirrors upstream status (typically `pending` during dunning) |
| `order.canceled`         | `OrderCanceled`                                                   | `CancelOrderOnCanceled`        | `OrderWriter::update` (mirrors `canceled` status)    |
| `order.chargeback_received` | `OrderChargebackReceived`                                   | `SyncChargebackOnStatusChange` *(opt-in, persistence)* | `ChargebackWriter::store` (new) |
| `order.chargeback_reversed` | `OrderChargebackReversed`                                   | `SyncChargebackOnStatusChange` *(opt-in, persistence)* | `ChargebackWriter::update` (existing) |
| `refund.completed` / `refund.failed` / `refund.canceled` | `RefundCompleted` / `RefundFailed` / `RefundCanceled` | `SyncRefundOnStatusChange` *(opt-in)* | `RefundWriter::store` (new) / `::update` (existing) |
| `subscription.started`   | `SubscriptionStarted`                                             | `SyncSubscriptionOnStarted`    | `SubscriptionWriter::store` (new) / `::update` (existing) |
| `subscription.billing_updated` | `SubscriptionBillingUpdated`                               | `SyncSubscriptionOnBillingUpdated` | `SubscriptionWriter::update` (refreshes mandate)    |
| `subscription.updated`   | `SubscriptionUpdated`                                            | `SyncSubscriptionOnUpdated`    | `SubscriptionWriter::update` (immediate plan/price/quantity change) |
| `subscription.update_scheduled` | `SubscriptionUpdateScheduled`                           | — (dispatched only)            | none — change applies next cycle; target values in `scheduledUpdate` |
| `subscription.resumed`   | `SubscriptionResumed`                                             | `ResumeSubscriptionOnResumed`  | `SubscriptionWriter::update` (clears end date)       |
| `subscription.canceled`  | `SubscriptionCanceledImmediately` / `SubscriptionCanceledWithGracePeriod` | `CancelSubscriptionOnCanceled` | `SubscriptionWriter::update`                         |
| `subscription.cancellation_grace_period_completed` | `SubscriptionCancellationGracePeriodCompleted` | `EndSubscriptionOnGracePeriodCompleted` | `SubscriptionWriter::update` (stamps actual end date) |
| `checkout.paid` / `checkout.failed` / `checkout.canceled` / `checkout.expired` | `CheckoutPaid` / `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` | — (dispatched only) | none — driver-handled |
| `webhook.setup`          | `WebhookSetupReceived`                                            | — (dispatched only)            | none — endpoint verification ping; acknowledge with `2xx` |

`OrderWriter::store`, `SubscriptionWriter::store`, and `RefundWriter::store` may return `null` if your driver can't route the data (see the adapter recipe below). Built-in reactions tolerate null — `SyncSubscriptionOnStarted` skips its follow-up `SubscriptionWasCreatedFromWebhook` dispatch (and `StoreOrderOnPaid` its `OrderWasCreatedFromWebhook` dispatch) when store returns null. Both driver-side events fire exactly once per brand-new local row (not on the update path).

`subscription.billing_updated`, `subscription.updated`, `subscription.resumed`, and `order.canceled` are find-or-skip: they update an existing local record but never create one. `subscription.billing_updated` carries the fresh mandate (card last-4, masked IBAN) in its signed payload, so the stored mandate stays in step with the payment method on file without an API call; `subscription.resumed` clears the stored end date so a resume reactivates the derived state; `order.canceled` mirrors Vatly's `canceled` status onto the local order.

**Subscription changes** come in two flavours. `subscription.updated` is an **immediate** plan / price / interval / quantity change — `SyncSubscriptionOnUpdated` refreshes the stored plan, name, and quantity from the signed payload (price is not persisted locally; fluent's `Store*Data` DTOs track plan/name/quantity, not the recurring money). `subscription.update_scheduled` is a change **scheduled for the next billing cycle**: the subscription's current state is unchanged, so there is no built-in reaction — the typed `SubscriptionUpdateScheduled` event carries the target values in a typed `scheduledUpdate` (`Vatly\API\Types\ScheduledSubscriptionUpdate`: plan id, name, description, base price, quantity, interval, interval count) and is dispatched for you to handle (e.g. warn the customer of an upcoming price change).

**Refunds** are opt-in: supply a `RefundRepositoryInterface` via `Wiring(refunds: …)` and the built-in `SyncRefundOnStatusChange` reaction persists `refund.*` webhooks (store-or-update, like orders) — unblocking terminal-state refund reconciliation. Omit it and the typed refund events are still dispatched for you to handle. The refund webhook payload already carries the full tax breakdown (like `order.paid`), so the event is built straight from it — no API call.

Read the refunds back idiomatically with `RefundReader::listForOrder` / `listForCustomer`, or via the handle: `$vatly->order($localOrder)->refunds()` returns the `RefundInterface[]` recorded against that order (local read, no API call; empty array when no refund repo is wired).

The order's reversal progress is read live from the Vatly API rather than synthesized into a local status — the order's own `status` stays terminal `paid`. `OrderHandle` exposes `reversedSubtotal()` / `refundableSubtotal()` (integer cents) and `isReversed()` / `isPartiallyReversed()` / `isFullyReversed()`, fetched once and memoized per handle instance. Because the API's `reversedSubtotal` combines refunds **and** chargebacks, these helpers answer "did money come back, and how much" regardless of how it was reversed.

**Chargebacks** mirror refunds and are opt-in: supply a `ChargebackRepositoryInterface` via `Wiring(chargebacks: …)` and the built-in `SyncChargebackOnStatusChange` reaction persists `order.chargeback_*` webhooks store-or-update (storing on receipt, updating on reversal). It does **not** mutate the order's status — the order stays `paid`, and whether money came back (chargebacks included) is read via the `OrderHandle` reversal helpers above. Omit the repository and the typed `OrderChargebackReceived` / `OrderChargebackReversed` events are still dispatched for you to handle (e.g. suspend access on receipt, reinstate on reversal). The chargeback webhook payload already carries the customer id, dispute status, and full tax breakdown, so the events are built straight from it (no second API call) and the reversed VAT can be reconciled directly. Read chargebacks back via `ChargebackReader::listForOrder` / `listForCustomer` or `$vatly->order($localOrder)->chargebacks()`.

**Checkout events** are dispatched only — no built-in reaction. The `checkout.*` deliveries carry the full Checkout resource (status, `customerId`, `orderId`, `metadata`). Use `CheckoutPaid` for an analytics/receipt handoff at the earliest "customer paid" moment — ahead of `order.paid` — and `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` for retry and cart-abandonment funnel hooks. `customerId` is nullable: an anonymous checkout only gets a customer attributed once payment completes.

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
    public function isTestmode(): bool { return $this->testmode; }
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
    public function isTestmode(): bool         { return (bool) $this->txn->testmode; }
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

**Full walkthrough:** [docs/recipes/host-owns-its-tables.md](docs/recipes/host-owns-its-tables.md) — covers the adapter wrapper, confirming (not duplicating) rows in `store()`, `findByVatlyId` as the idempotency hinge for safe re-deliveries, and discriminating renewal-vs-initial payments inside one `store()`.

</details>

### 5. Implement the three repository contracts

Each entity-side contract has three methods. See [src/Contracts](src/Contracts) for signatures.

- `SubscriptionRepositoryInterface` — `findByVatlyId`, `store`, `update`
- `OrderRepositoryInterface` — `findByVatlyId`, `store`, `update`
- `RefundRepositoryInterface` — `findByVatlyId`, `listForOrder`, `listForCustomer`, `store`, `update` (**optional** — only needed to persist `refund.*` webhooks)
- `ChargebackRepositoryInterface` — `findByVatlyId`, `listForOrder`, `listForCustomer`, `store`, `update` (**optional** — only needed to persist `order.chargeback_*` webhooks)
- `WebhookCallRepositoryInterface` — record received webhook calls (audit log)

`StoreSubscriptionData` and `StoreOrderData` both carry an optional `hostCustomerId` resolved from the binding repo when fluent persists from a webhook reaction. Use it to fill your host-side owner column when it's set, and accept `null` for the anonymous-checkout flow.

Every `Store*Data` (order/subscription/refund/chargeback) also carries a required `testmode` bool, sourced from the originating Vatly record. Persist it on your local row and surface it through the matching entity interface's `isTestmode()` — this keeps test and live records segregated and lets you select the matching API key per record (vs. the global config mode). Note this is per-record `isTestmode()`, distinct from `ConfigurationInterface::isTestmode()`, which reflects the configured key.

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

> **If your repo needs to call back into the SDK** — e.g. `GetOrder` to read a fresh resource on demand — don't inject `Vatly` directly. `Vatly` is being constructed *with* your repo, so a direct dependency is circular. Instead, inject a lazy resolver (your host's container, a singleton accessor, or a closure that returns `Vatly`) and resolve at call time.
>
> ```php
> // ✗ Circular — $vatly doesn't exist yet at Wiring-construction time:
> new Vatly(new Wiring(orders: new MyOrderRepository($vatly), …));
>
> // ✓ Closure resolver — the repo only touches Vatly at call time:
> new Vatly(new Wiring(orders: new MyOrderRepository(fn () => $container->get(Vatly::class)), …));
>
> // ✓ Singleton accessor — same idea via a static entry point:
> new Vatly(new Wiring(orders: new MyOrderRepository(Plugin::vatly(...)), …));
> ```
>
> Inside the repo, resolve lazily: `($this->vatly)()->getOrder()->execute($vatlyId)` for the closure form, or `Plugin::vatly()->getOrder()->execute($vatlyId)` for the accessor form.

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

// Vatly ids are prefixed — subscription plans are `subscription_plan_…`,
// one-off products `one_off_product_…`. Find them in the Vatly dashboard
// or via `GET /subscription-plans`.

// Create a checkout — pass in a CustomerProfile carrying whatever the host knows.
use Vatly\Fluent\CustomerProfile;

// Each checkout item id is a Vatly product: `one_off_product_…` for a one-off
// product or `subscription_plan_…` for a subscription plan. Create the product
// in the Vatly dashboard first — there is no API to make one on the fly.
$checkout = $vatly
    ->checkoutBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->withRedirectUrlSuccess('https://app.example.com/done')
    ->withRedirectUrlCanceled('https://app.example.com/oops')
    ->create([['id' => 'one_off_product_3Qb8Wz1Yt', 'quantity' => 1]], '...', '...');

// Need a custom amount? Override the product's price with a Money object
// (`value` is a decimal string; precision follows the currency). The item id
// still points at a pre-configured product.
$checkout = $vatly
    ->checkoutBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->create([[
        'id' => 'one_off_product_3Qb8Wz1Yt',
        'quantity' => 1,
        'price' => ['value' => '49.99', 'currency' => 'EUR'],
    ]], 'https://app.example.com/done', 'https://app.example.com/oops');

// Subscribe
$checkout = $vatly
    ->subscriptionBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->toPlan('subscription_plan_7Hd9Kf2Lm')
    ->create();

// Subscribe with a free trial. withTrialDays() is the whole-day form;
// withTrialEndsAt() takes a DateTimeInterface and rounds up to whole days
// (Vatly's trial input is day-granular) so the trial never ends early.
$checkout = $vatly
    ->subscriptionBuilder(new CustomerProfile(vatlyId: $user->vatly_id))
    ->toPlan('subscription_plan_7Hd9Kf2Lm')
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

> **Source of truth for payload fields.** Fluent passes checkout/subscription payloads through to `vatly-api-php`, whose schema is the canonical [`openapi.yaml`](https://docs.vatly.com/openapi.yaml) (also vendored at `vendor/vatly/vatly-api-php/openapi.yaml`). For the authoritative API request/response fields and the incoming webhook delivery shape, read the spec rather than guessing from an example.

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
$vatly->getApiClient();                            // raw VatlyApiClient (also: ->webhookEndpoints, ->subscriptionPlans, ->oneOffProducts, ->testHelpers, ->webhookEvents)
$vatly->getSignatureVerifier();                    // raw webhook signature verifier
$vatly->getWebhookEventFactory();                  // api-php Vatly\API\Webhooks\WebhookEventFactory (parses webhook payloads)

// Actions (lazy, cached)
$vatly->createCustomer();    $vatly->getCustomer();    $vatly->updateCustomer();
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
- `ChargebackRepositoryInterface` — chargeback persistence (optional). Splits into `ChargebackReader` (find + `listForOrder` / `listForCustomer`) + `ChargebackWriter` (store/update).
- `WebhookCallRepositoryInterface` — webhook audit log (write-only by nature)
- `EventDispatcherInterface` — fire domain events
- `ConfigurationInterface` — API key, URL, version, webhook secret, redirect defaults
- `WebhookReactionInterface` — extension point for adding your own webhook reactions. Compose multiple via `Webhooks\Reactions\WebhookReactionChain` (variadic constructor; the chain itself implements the same interface).

## Domain events

Dispatched by webhook reactions through your `EventDispatcherInterface`. Subscribe to them in your framework's event bus.

- `WebhookReceived` — raw webhook envelope (typed shape; `object` is the resource payload)
- `OrderPaid` — order with full `taxSummary` breakdown, ready to materialize local invoices
- `OrderPaymentFailed` — failed payment attempt (typically the start of dunning); carries the full order shape, mirroring `OrderPaid`
- `OrderCanceled`
- `OrderChargebackReceived` / `OrderChargebackReversed` — dispute signals carrying the affected `orderId`
- `RefundCompleted` / `RefundFailed` / `RefundCanceled` — each with full `taxSummary` breakdown
- `SubscriptionStarted`
- `SubscriptionBillingUpdated` — billing/mandate changed; carries the refreshed mandate summary
- `SubscriptionUpdated` — an immediate plan/price/interval/quantity change (effective now)
- `SubscriptionUpdateScheduled` — a change scheduled for the next billing cycle; target values in `scheduledUpdate`
- `SubscriptionResumed`
- `SubscriptionCanceledImmediately`
- `SubscriptionCanceledWithGracePeriod`
- `SubscriptionCancellationGracePeriodCompleted`
- `CheckoutPaid` / `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired`
- `WebhookSetupReceived` — endpoint verification ping (`webhook.setup`); dispatched-only, acknowledge with `2xx`
- `UnsupportedWebhookReceived`

Driver-side events (namespace `Vatly\Fluent\Events`, carrying the freshly persisted local record — fired exactly once per brand-new row):

- `SubscriptionWasCreatedFromWebhook` — dispatched by `SyncSubscriptionOnStarted` on a brand-new subscription
- `OrderWasCreatedFromWebhook` — dispatched by `StoreOrderOnPaid` on a brand-new order

## Testing

```bash
composer test
```

### Test helpers for consumers

`Vatly\Fluent\Testing` ships fakes so consumers don't hand-roll a Mockery stub for every fluent entry point (which breaks the moment fluent grows a method):

```php
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Testing\FakeCheckout;

$fake = (new FakeVatly())->onSubscriptionCreate(
    fn (string $planId) => FakeCheckout::make('https://checkout.vatly.test/chk_1'),
);
$this->app->instance(Vatly::class, $fake); // drop-in: FakeVatly extends Vatly

$this->get('/vatly/subscription-checkout/plan_pro')
    ->assertRedirect('https://checkout.vatly.test/chk_1');

$fake->assertSubscriptionCreated('plan_pro');
$fake->assertNothingCanceled();
```

- **`FakeVatly`** — drop-in `Vatly` that hands out recording builders/handles and returns scriptable `Checkout`s (`onSubscriptionCreate` / `onCheckoutCreate` / `withDefaultCheckout`).
- **`FakeCheckout::make($url)`** — a minimal `Checkout` with a working `links->checkoutUrl->href`.
- **Assertions** — `assertSubscriptionCreated($planId)`, `assertCheckoutCreated(productId:)`, `assertSubscriptionSwapped(from:, to:)`, `assertSubscriptionCanceled($id)`, `assertNothingCanceled()`, `assertNothingCreated()`.

Swap/cancel/resume routed through `$fake->subscription($localSub)` are recorded too. Ships in-package (like Cashier's helpers); the PHPUnit dependency is only touched from the `assert*` methods.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
