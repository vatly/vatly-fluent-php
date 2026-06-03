# Webhook flow: event → reaction → repo method → fields

A driver author shouldn't have to read four directories (the event DTOs in `vatly-api-php`'s `Vatly\API\Webhooks\Events\*`, `src/Webhooks/Reactions/`, `WebhookEventFactory`, `src/Contracts/`) to answer *"if I implement `OrderWriter::store`, which webhooks flow into it and with what data?"* This page is that single reference.

> **Where the event DTOs live:** the typed webhook events (`OrderPaid`, `OrderPaymentFailed`, `SubscriptionStarted`, …) are owned by **`vatly-api-php`** under `Vatly\API\Webhooks\Events\*` and consumed by fluent — fluent no longer ships its own `src/Events/` copies (only the driver-side `SubscriptionWasCreatedFromWebhook` / `OrderWasCreatedFromWebhook` / `NullEventDispatcher` remain in `Vatly\Fluent\Events\`). Their `taxSummary` field is a `Vatly\API\Types\TaxSummaryCollection` whose items expose `taxRate` (a `TaxSummaryRate`) + `amount` (a `Money`); call `$item->amount->toCents()` for integer cents. Order lines are `Vatly\API\Types\OrderLineData[]` (note: `Vatly\API\Types\`, not the old `Vatly\API\Data\`), and each line's `basePrice` / `total` / `subtotal` are likewise `Money`. The orchestration (`WebhookEventFactory`, the reactions, `WebhookProcessor`) stays in fluent.

> **Event money is `Money`, not int cents (api-php ≥ `0.1.0-alpha.18`):** the `total` / `subtotal` fields on the money-bearing events are `Vatly\API\Types\Money` value objects (decimal-string `value` + `currency`), not raw integer cents. Read the currency off the Money (`$event->total->currency`) and flatten to integer cents with `$event->total->toCents()`. The **standalone `currency` field was removed** from `OrderPaid`, `OrderPaymentFailed`, `RefundCompleted`, `RefundFailed`, and `RefundCanceled` (their `total` / `subtotal` are non-null `Money`). The chargeback events `OrderChargebackReceived` / `OrderChargebackReversed` are the exception: their `total` / `subtotal` are **nullable** (`?Money`, sparse webhooks carry no amount) and they **keep** a standalone `currency` string. Fluent's built-in reactions flatten `Money → int` at the persistence edge, so the driver-facing `Store*Data` / `Update*Data` DTOs still carry integer-cents `total` / `subtotal` and a `currency` string — drivers are unaffected.

The [README's "Webhook pipeline at a glance"](../README.md#webhook-pipeline-at-a-glance) table is the quick version; this one adds the **fields drivers care about** column so you know exactly what each `Store*Data` / event carries.

## Enrichment

Some events are built straight from the webhook payload; others are **enriched** with a follow-up API GET before dispatch, because the raw webhook is sparse (gross total only — no subtotal/tax breakdown). Enrichment matters because it determines whether a transient API error can block the delivery:

- **`order.paid`, `order.payment_failed`, `refund.*`** — enriched via `GetOrder` / `GetRefund`. Tax data is compliance-critical, so these **rethrow** on enrichment failure (Vatly retries the delivery) rather than persist a degraded row.
- **`subscription.started`, `subscription.billing_updated`** — enriched via `GetSubscription` for the mandate summary, but **fall back** to the webhook payload (which embeds the mandate) on failure — enrichment here is non-lossy.
- **`order.chargeback_*`** — enriched via `GetChargeback` when that action is wired (customer id, dispute status, tax breakdown), but **fall back** to the sparse payload otherwise — best-effort.
- **`order.canceled`, `checkout.*`, `subscription.cancellation_grace_period_completed`** — built straight from the payload; no GET needed.

Refund events are only enriched (and thus only reported as supported) when a `GetRefund` action is wired — otherwise they degrade to `UnsupportedWebhookReceived`.

## Full matrix

| Vatly event | Typed event | Built-in reaction | Repo method called | Fields drivers care about |
| --- | --- | --- | --- | --- |
| `order.paid` | `OrderPaid` | `StoreOrderOnPaid` | `OrderWriter::store(StoreOrderData)` if `findByVatlyId` is null (then dispatches `OrderWasCreatedFromWebhook`, skipped if store returns null), else `OrderWriter::update(…, UpdateOrderData)` | `hostCustomerId` (pre-resolved via binding repo), `customerId`, `total`, `subtotal`, `currency`, `invoiceNumber`, `paymentMethod`, `taxSummary`, `metadata`, `lines` |
| `order.payment_failed` | `OrderPaymentFailed` | `StoreOrderOnPaymentFailed` | `OrderWriter::store(StoreOrderData)` if new, else `OrderWriter::update(…, UpdateOrderData)` | same as `order.paid` (sans `lines`); persists whatever status the enriched order carries (typically `pending` during dunning) — mirrors, never synthesises a `failed` status |
| `order.canceled` | `OrderCanceled` | `CancelOrderOnCanceled` | `OrderWriter::update(…, UpdateOrderData{status})` | `status` (== `canceled`) — find-or-skip, never creates |
| `refund.completed` / `refund.failed` / `refund.canceled` | `RefundCompleted` / `RefundFailed` / `RefundCanceled` | `SyncRefundOnStatusChange` *(opt-in: needs `RefundRepositoryInterface`)* | `RefundWriter::store(StoreRefundData)` if new, else `RefundWriter::update(…, UpdateRefundData)` | `refundId`, `originalOrderId`, `customerId`, `hostCustomerId`, `status`, `total`, `subtotal`, `currency`, `taxSummary` |
| `subscription.started` | `SubscriptionStarted` | `SyncSubscriptionOnStarted` | `SubscriptionWriter::store(StoreSubscriptionData)` then dispatches `SubscriptionWasCreatedFromWebhook` (skipped if store returns null) | `hostCustomerId`, `customerId`, `planId`, `name`, `quantity`, `type`, `mandate` |
| `subscription.billing_updated` | `SubscriptionBillingUpdated` | `SyncSubscriptionOnBillingUpdated` | `SubscriptionWriter::update(…, UpdateSubscriptionData{mandate})` | `mandate` (card last-4 / masked IBAN) — find-or-skip |
| `subscription.resumed` | `SubscriptionResumed` | `ResumeSubscriptionOnResumed` | `SubscriptionWriter::update(…, UpdateSubscriptionData{endsAt: null})` | clears `endsAt` — find-or-skip |
| `subscription.canceled_immediately` | `SubscriptionCanceledImmediately` | `CancelSubscriptionOnCanceled` | `SubscriptionWriter::update(…, UpdateSubscriptionData{endsAt})` | `endsAt` (== now) |
| `subscription.canceled_with_grace_period` | `SubscriptionCanceledWithGracePeriod` | `CancelSubscriptionOnCanceled` | `SubscriptionWriter::update(…, UpdateSubscriptionData{endsAt})` | `endsAt` (future grace end) |
| `subscription.cancellation_grace_period_completed` | `SubscriptionCancellationGracePeriodCompleted` | `EndSubscriptionOnGracePeriodCompleted` | `SubscriptionWriter::update(…, UpdateSubscriptionData{endsAt})` | `endsAt` (actual end; self-heals a missed/out-of-order cancel webhook) |
| `order.chargeback_received` / `order.chargeback_reversed` | `OrderChargebackReceived` / `OrderChargebackReversed` | `SyncChargebackOnStatusChange` *(opt-in: needs `ChargebackRepositoryInterface`)* | `ChargebackWriter::store(StoreChargebackData)` if new, else `ChargebackWriter::update(…, UpdateChargebackData)` | `chargebackId`, `originalOrderId`, `customerId`, `hostCustomerId`, `status`, `total`, `subtotal`, `currency`, `taxSummary`, `reason` |
| `checkout.paid` / `checkout.failed` / `checkout.canceled` / `checkout.expired` | `CheckoutPaid` / `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` | — *(dispatched only)* | none — driver-handled | `checkoutId`, `customerId` (nullable for anonymous), `orderId`, `status`, `metadata` |
| `webhook.setup` | `WebhookSetupReceived` | — *(dispatched only)* | none — endpoint verification ping; acknowledge with `2xx` | raw `WebhookReceived` envelope (`object` is the secret-free endpoint config) |
| *(any unmapped event)* | `UnsupportedWebhookReceived` | — | none | raw `WebhookReceived` envelope |

## Reading the matrix

- **"store if new, else update"** — every entity reaction first calls `findByVatlyId()`. A null return routes to `store()`; a hit routes to `update()`. This is the idempotency hinge: once `store()` writes the Vatly id back, webhook re-deliveries safely hit `update()`. See the [host-owns-its-tables recipe](recipes/host-owns-its-tables.md).
- **"find-or-skip"** — the reaction only `update()`s an existing local record and never creates one (`order.canceled`, `subscription.billing_updated`, `subscription.resumed`).
- **`hostCustomerId`** — resolved from your `CustomerBindingRepository` *before* the reaction calls your writer, so `store()` can fill the owner column directly. It's `null` for unattributed / anonymous-checkout rows; accept that.
- **store may return `null`** — `OrderWriter`, `SubscriptionWriter`, `RefundWriter`, and `ChargebackWriter` `store()` may return `null` when the driver can't route the data. Built-in reactions tolerate it.
- **opt-in reactions** register only when their repository is wired into `Wiring`. Without it, the typed events still dispatch for you to handle yourself.
- **order reversal progress is not a webhook reaction.** Whether — and how much of — an order was returned to the customer (by refund *or* chargeback) is read from the live API Order via `OrderHandle::reversedSubtotal()` / `refundableSubtotal()` / `isReversed()` / `isPartiallyReversed()` / `isFullyReversed()`. The order's own `status` stays terminal `paid`; fluent never synthesizes a local refunded/chargeback status.
