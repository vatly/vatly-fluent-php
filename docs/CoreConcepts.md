# Core concepts

Connect Fluent to your database without replacing your models. Build a driver by implementing the storage contracts, then pass those implementations to `Wiring`:

```php
use Vatly\Fluent\Contracts\ConfigurationInterface;
use Vatly\Fluent\Contracts\CustomerBindingRepository;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Wiring;

function billing(
    ConfigurationInterface $config,
    CustomerBindingRepository $bindings,
    SubscriptionRepositoryInterface $subscriptions,
    OrderRepositoryInterface $orders,
    WebhookCallRepositoryInterface $webhookCalls,
    EventDispatcherInterface $events,
): Vatly {
    return new Vatly(new Wiring(
        config: $config,
        subscriptions: $subscriptions,
        orders: $orders,
        webhookCalls: $webhookCalls,
        events: $events,
        customerBindings: $bindings,
    ));
}
```

Register that instance in your application's container. Fluent does not ship a database driver or migrations. Laravel users should use [vatly-laravel](https://github.com/vatly/vatly-laravel) instead of writing this wiring.

## Choose the billable

Your billable is the user, team, company, or tenant that pays. There is no `Billable` interface or required base model. Give Fluent a `CustomerProfile` with a Vatly customer ID, or use [Customers](Customers.md) to create and bind one to your host ID.

A profile is input, not a stored customer. Supplying only its email to a checkout builder does not create or bind a customer. Create the customer explicitly, or use an empty profile for an anonymous checkout.

## Save local billing state

| Contract | Your implementation does |
| --- | --- |
| `CustomerBindingRepository` | Maps host IDs to Vatly customer IDs in both directions; records unattributed customers. |
| `SubscriptionRepositoryInterface` | Finds by Vatly ID, stores a subscription, updates it. |
| `OrderRepositoryInterface` | Finds by Vatly ID, stores an order, updates it. |
| `WebhookCallRepositoryInterface` | Records received deliveries and cleans up old audit rows. It does not skip duplicate processing. |
| `EventDispatcherInterface` | Dispatches typed events to your listeners. `NullEventDispatcher` is available if you need no listeners. |

Repositories return your objects implementing `SubscriptionInterface` or `OrderInterface`. Wrap existing host records rather than copying them; see [Host tables](host-owns-its-tables.md). Reader and writer contracts are also available separately when a component needs only one role.

Enforce unique Vatly IDs in storage and binding uniqueness in both directions. Accept `hostCustomerId: null` on store data: anonymous payments may arrive before a customer is linked. Entity `store()` methods may return `null` when a record genuinely does not belong to your integration. Throw on a storage failure so the delivery can retry.

## Apply partial updates correctly

`Store*Data` and `Update*Data` carry local persistence data. Amounts are integer cents with a separate currency. SDK resources and money-bearing webhook events use `Money` objects instead; use `toCents()` at your own persistence boundary.

In updates, `null` normally means leave the stored value alone. Subscription updates have explicit `clearEndsAt` and `clearMandate` flags. A non-null mandate replaces both its method and masked identifier; do not keep an old card's last four digits when a new mandate has no identifier.

Persist `testmode` from store data and expose it through the entity's `isTestmode()`. Select the matching key in your application; storing the flag does not switch keys automatically.

`DerivesSubscriptionState` implements the subscription state predicates from `getEndsAt()`: no end date means active, a future end date means a valid grace period, a past date means ended. These predicates are not a full entitlement policy for trials, failed payments, or disputes. Your app decides access.

## Add optional records

`Wiring` also accepts `refunds`, `chargebacks`, and `orderLines`. Refund and chargeback repositories enable their persistence reactions. Order lines need your order writer to persist the `StoreOrderData::$lines` supplied on a new paid order; wiring a line reader alone does not save lines.

Use [Webhooks](Webhooks.md) for transactions and [Event flow](webhook-flow.md) for the event-to-storage mapping. Field details live in the [API reference](https://docs.vatly.com/api-reference).
