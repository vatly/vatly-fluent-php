# Event flow

Choose the event that matches your task. To listen for a paid order, implement your event dispatcher and register it in `Wiring`. This small adapter forwards typed events to an application callback:

```php
use Vatly\Fluent\Contracts\EventDispatcherInterface;

final class BillingEvents implements EventDispatcherInterface
{
    public function __construct(private Closure $listener) {}

    public function dispatch(object $event): void
    {
        ($this->listener)($event);
    }
}
```

In production, let that callback write outbox jobs on the same transaction as your repositories. It receives the typed event after the built-in reactions have run. Follow [Webhooks](Webhooks.md) for verification, deduplication, and the transaction boundary.

## Events that save records

| Event | Built-in persistence |
| --- | --- |
| `order.paid` | Find by Vatly ID; store a new order or update the existing one. New store data includes lines. |
| `order.payment_failed` | Store or update the order with the payload's status, usually `pending` during recovery. It does not invent a `failed` order status. New store data includes lines, so a renewal paid on retry keeps its lines. |
| `order.canceled` | Update an existing order's status and cancellation reason; skip if missing. |
| `subscription.started` | Store a new subscription or update an existing one. |
| `subscription.updated` | Update an existing subscription's plan, name, and quantity for an immediate change. |
| `subscription.billing_updated` | Update an existing subscription's mandate summary. |
| `subscription.resumed` | Clear the end date on an existing subscription. |
| `subscription.canceled_immediately`, `subscription.canceled_with_grace_period` | Update the existing subscription's confirmed end date and reason. |
| `subscription.canceled_for_nonpayment` | End the existing subscription after recovery is exhausted, with reason `payment_failure`. |
| `subscription.cancellation_grace_period_completed` | Stamp the actual end date on an existing subscription. |
| `refund.completed`, `refund.failed`, `refund.canceled` | Store or update only when a refund repository is wired. |
| `order.chargeback_received`, `order.chargeback_reversed` | Store or update only when a chargeback repository is wired. The order stays `paid`. |

Order and subscription creation resolve `hostCustomerId` through your binding repository. It can be `null`. The store DTOs carry `testmode`; persist it. Order, refund, and chargeback store DTOs also carry totals, currency, and tax details.

All entity stores can return `null` for an unroutable record. Successful new paid-order and subscription stores dispatch `OrderWasCreatedFromWebhook` and `SubscriptionWasCreatedFromWebhook`. Updates do not dispatch those local-created events. This distinction is not a concurrency lock: enforce unique IDs and transactions in storage.

Partial updates use `null` for no change. Persist cancellation reasons if your host needs them. Subscription resume uses `clearEndsAt: true`; honor it in your writer.

## Events your app handles

| Event | Useful for |
| --- | --- |
| `subscription.update_scheduled` | Showing a pending next-cycle change from the typed `scheduledUpdate`. No current local plan change. |
| `checkout.paid` | Checkout analytics or handoff. Use `order.paid` for full order persistence. |
| `checkout.failed`, `checkout.canceled`, `checkout.expired` | Retry and abandoned-cart flows. |
| `webhook.setup` | Endpoint setup verification; acknowledge with 2xx. |
| Product update, approval, rejection, archive, and unarchive events | Catalog notifications; no built-in storage. |
| Unknown event names | `UnsupportedWebhookReceived`, so you can observe new events without inventing a handler. |

Refund and chargeback events still dispatch when their optional repositories are absent. Checkout events do not create local orders or bindings on their own. Anonymous checkout events may have no customer ID yet.

## Money and snapshots

Typed event classes are owned by `vatly-api-php` in `Vatly\API\Webhooks\Events`. Money-bearing events use `Money`, not integer amounts. For paid orders and refunds, read currency from `total->currency`; use `total->toCents()` for local integer cents. Chargeback amounts are nullable at the event contract level and retain a separate currency field.

Fluent builds events from the signed resource snapshot without a follow-up GET. Its reactions flatten money to integer cents in persistence DTOs. They do not save subscription prices or build entitlement policy for you. Reversal totals are read live through the [order handle](Orders.md), not synthesized into order statuses.

See [Host tables](host-owns-its-tables.md) for routing initial payments and renewals, and the [API reference](https://docs.vatly.com/api-reference) for complete payload fields.
