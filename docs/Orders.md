# Orders

Show an invoice for a local order without rebuilding Vatly's invoice UI. Here `$localOrder` implements `OrderInterface` and belongs to the signed-in account:

```php
$order = $vatly->order($localOrder);
$invoiceUrl = $order->invoiceUrl();
$totalCents = $order->getTotal();
$currency = $order->getCurrency();
```

Render an invoice link only when `$invoiceUrl` is not `null`. `getTotal()` and `getCurrency()` read local state. `invoiceUrl()` fetches the live API order; it may have no invoice yet. Escape URLs and text in your templates.

Persist orders through [Webhooks](Webhooks.md). `order.paid` includes tax details and lines; your order writer owns how they map to your database. Do not create a second local order just because a delivery is retried.

## Show refunds and chargebacks

Read the net subtotal returned to the customer, regardless of whether it was a refund or chargeback:

```php
$order = $vatly->order($localOrder);
$reversedCents = $order->reversedSubtotal();
$remainingCents = $order->refundableSubtotal();
$partlyReversed = $order->isPartiallyReversed();
$fullyReversed = $order->isFullyReversed();
$refunds = $order->refunds();
$chargebacks = $order->chargebacks();
$lines = $order->lines();
```

Reversal amounts are net of tax, in integer cents. They come from the live API, not a sum of your local records. The handle fetches the API order once and shares that snapshot with invoice and reversal helpers. Build a new handle when you need a fresh snapshot.

The order remains `paid` after a reversal; do not invent a refunded order status. Your application decides what a refund or dispute means for access.

`refunds()`, `chargebacks()`, and `lines()` are local reads. Each returns an empty array if its optional repository is not wired. That does not prove the API has no such records. Wire refund and chargeback repositories to persist their events. Persist lines in your order writer and supply an order-line repository to read them back.

## Find a subscription's orders

List the local lines of every order a subscription generated, including the initial signup order, through the order-line repository you wired:

```php
$lines = $orderLines->listForSubscription($subscriptionId);
$orderIds = array_unique(array_map(fn ($line) => $line->getOrderId(), $lines));
```

Each line's `getSubscriptionId()` names its subscription, whatever its product. An initial signup line keeps referencing the plan (`productType` is `subscription_plan`); renewals reference the subscription. When your writer stores a line, save `$line->relatedResources?->subscriptionId` with it. It is `null` on one-off lines, on lines Vatly created before it carried the link, and on a signup line whose subscription was not yet available when the order was paid. `listForSubscription()` also returns renewal lines whose `productType` is `subscription` and `productId` is the subscription id, so renewals stored without the link still appear.

For refund creation, credit-note fields, and the full order schema, use the underlying SDK through `getApiClient()` and the [API reference](https://docs.vatly.com/api-reference). See [Event flow](webhook-flow.md) for persistence behavior.
