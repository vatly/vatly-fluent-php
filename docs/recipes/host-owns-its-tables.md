# Recipe: adapting fluent contracts to a host that already owns its tables

The README's driver guide implicitly assumes you're modeling subscriptions/orders from scratch in your own schema. The other common case is the inverse: you're bolting onto an ecosystem plugin — **FluentCart, PMPro, MemberPress, Easy Digital Downloads, WooCommerce**, a Cashier-style Spark app — that **already owns** its `subscriptions`, `orders`, and `customers` tables. You don't want a parallel set of tables; you want fluent's contracts to read and write the host's existing ones.

The v0.8 contract shape makes this clean (v0.5's `Billable` forced a host-model shape that was wrong for non-Laravel drivers). This recipe is the sanctioned pattern.

> The README has a condensed version of steps 1–2 under [*"What if my host already has Order / Subscription tables?"*](../../README.md#5-implement-the-three-repository-contracts). This page is the full walkthrough, including idempotency and renewal-vs-initial routing.

## 1. Adapter wrappers, not native implementations

Don't make the host's own Order/Subscription model implement `OrderInterface` — you usually can't edit it, and you don't want fluent's surface bleeding into the host's model. Instead wrap it in a thin adapter that holds the host record and exposes only the fluent surface:

```php
use Vatly\Fluent\Contracts\OrderInterface;

final class FluentCartOrder implements OrderInterface
{
    public function __construct(private OrderTransaction $txn) {}

    public function getVatlyId(): string        { return $this->txn->vatly_id; }
    public function getStatus(): string         { return $this->txn->status; }
    public function getInvoiceNumber(): ?string { return $this->txn->invoice_no; }
    public function getTotal(): int             { return (int) $this->txn->total; }
    public function getCurrency(): string       { return $this->txn->currency; }
    public function getPaymentMethod(): ?string { return $this->txn->payment_method; }
    public function isPaid(): bool              { return $this->txn->status === 'paid'; }

    /** Escape hatch for your own code that needs the underlying host record. */
    public function transaction(): OrderTransaction { return $this->txn; }
}
```

## 2. Route inside `store()` instead of literally storing

When the host already created the row at checkout time (before the webhook arrives), `OrderWriter::store(StoreOrderData)` is the place to **confirm** the existing row rather than `INSERT` a new one. Look up the host row by some signal the checkout stamped — most commonly a host id round-tripped through the Vatly order's `metadata` — update its status, write the Vatly id back, and return the adapter:

```php
public function store(StoreOrderData $data): ?OrderInterface
{
    $txnId = $data->metadata['fluentcart_transaction_id'] ?? null;
    if ($txnId === null) {
        return null; // anonymous / audit-only delivery — nothing local to attach to
    }

    $txn = OrderTransaction::find($txnId);
    if ($txn === null) {
        return null; // host row gone — tolerate rather than throw
    }

    $txn->vatly_id = $data->vatlyId; // <-- the idempotency hinge (see step 3)
    $txn->status   = $data->status;
    $txn->save();

    return new FluentCartOrder($txn);
}
```

Returning `null` is a first-class outcome: the built-in reactions tolerate it (a `SubscriptionWriter::store` returning null simply skips the follow-up `LocalSubscriptionCreated` dispatch).

## 3. `findByVatlyId` as the idempotency hinge

Every entity reaction calls `findByVatlyId()` first: a null return routes to `store()`, a hit routes to `update()`. So the moment `store()` writes the Vatly id onto the host row (step 2), **every subsequent re-delivery of that webhook hits `update()` instead of `store()`** — which is exactly what you want, because Vatly retries deliveries and you must be idempotent.

```php
public function findByVatlyId(string $vatlyId): ?OrderInterface
{
    $txn = OrderTransaction::where('vatly_id', $vatlyId)->first();

    return $txn !== null ? new FluentCartOrder($txn) : null;
}

public function update(OrderInterface $order, UpdateOrderData $data): OrderInterface
{
    $txn = $order->transaction();           // adapter escape hatch from step 1
    if ($data->status !== null)  { $txn->status = $data->status; }
    if ($data->invoiceNumber !== null) { $txn->invoice_no = $data->invoiceNumber; }
    $txn->save();

    return $order;
}
```

The result: a `store()` that *confirms* a pre-existing row on first delivery, and an `update()` that *reconciles* it on every re-delivery — with no duplicate rows and no host-side unique-constraint violations.

## 4. Discriminating renewal vs. initial payment inside `store()`

Some ecosystems split "first payment" and "renewal payment" into different service calls. FluentCart is the canonical example: `Confirmations::confirmPaymentSuccessByCharge` for the initial order vs. `SubscriptionRenewal::recordRenewalPayment` for renewals. A single `order.paid` webhook flows into your one `OrderWriter::store` — so you have to discriminate.

Use the same metadata round-trip plus `$data->hostCustomerId` (pre-resolved by fluent via your `CustomerBindingRepository`):

```php
public function store(StoreOrderData $data): ?OrderInterface
{
    // Initial checkout stamped the host transaction id onto the Vatly order's
    // metadata; renewals (created server-side by Vatly) carry no such id.
    $txnId = $data->metadata['fluentcart_transaction_id'] ?? null;

    if ($txnId !== null && ($txn = OrderTransaction::find($txnId)) !== null) {
        // INITIAL payment — confirm the row the customer's checkout created.
        Confirmations::confirmPaymentSuccessByCharge($txn, $data->vatlyId);

        return new FluentCartOrder($txn->refresh());
    }

    // RENEWAL — no initial host row. Resolve the owning customer (via the
    // binding fluent already mapped) and let the host mint a renewal order.
    if ($data->hostCustomerId === null) {
        return null; // unattributed renewal — nothing to route to
    }

    $renewal = SubscriptionRenewal::recordRenewalPayment(
        customerId: $data->hostCustomerId,
        vatlyOrderId: $data->vatlyId,
        total: $data->total,
        currency: $data->currency,
    );

    return new FluentCartOrder($renewal);
}
```

The discriminator is "did *this* charge originate from a host-side checkout?" — answered by the presence of the metadata id. `hostCustomerId` then tells you *who* the renewal belongs to without an API re-fetch.

## Summary

| Step | Contract surface | Pattern |
| --- | --- | --- |
| Wrap | `OrderInterface` / `SubscriptionInterface` | thin adapter over the host model; never make the host model implement the contract |
| Confirm | `…Writer::store` | look up + confirm the existing host row; write the Vatly id back; return `null` when unroutable |
| Idempotency | `…Reader::findByVatlyId` | once the Vatly id is on the host row, re-deliveries route to `update()` |
| Route | `…Writer::store` | discriminate initial vs. renewal via metadata id + `hostCustomerId` |

A concrete end-to-end implementation lives in the FluentCart driver (`vatly-fluentcart-wp`).
