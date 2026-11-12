# Host tables

Reuse an application's existing orders instead of creating parallel Fluent tables. Wrap its record in `OrderInterface`, then let your repository confirm that record when the payment webhook arrives.

This example uses PDO and an existing `host_orders` table with `id`, `vatly_id`, `status`, `invoice_number`, `total`, `currency`, `payment_method`, and `testmode` columns. `id` is the host primary key; make `vatly_id` unique. `$db` uses exception mode. Keep additional ownership, tax, and fulfillment fields required by your application.

## Wrap the host record

```php
use Vatly\Fluent\Contracts\OrderInterface;

final class HostOrder implements OrderInterface
{
    public function __construct(public readonly array $row) {}

    public function getVatlyId(): string { return $this->row['vatly_id']; }
    public function getStatus(): string { return $this->row['status']; }
    public function getInvoiceNumber(): ?string { return $this->row['invoice_number']; }
    public function getTotal(): int { return (int) $this->row['total']; }
    public function getCurrency(): string { return $this->row['currency']; }
    public function getPaymentMethod(): ?string { return $this->row['payment_method']; }
    public function isPaid(): bool { return $this->getStatus() === 'paid'; }
    public function isTestmode(): bool { return (bool) $this->row['testmode']; }
}
```

## Confirm once, reconcile on retry

Create your host order before checkout. Stamp its server-chosen ID in `withMetadata(['host_order_id' => ...])`, as shown in [Checkouts](Checkouts.md). Wire this repository as `Wiring(orders: ...)`:

```php
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\Data\UpdateOrderData;

final class HostOrders implements OrderRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findByVatlyId(string $vatlyId): ?OrderInterface
    {
        $query = $this->db->prepare('SELECT * FROM host_orders WHERE vatly_id = ?');
        $query->execute([$vatlyId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : new HostOrder($row);
    }

    public function store(StoreOrderData $data): ?OrderInterface
    {
        $hostId = $data->metadata['host_order_id'] ?? null;
        if ($hostId === null) {
            return null;
        }

        $query = $this->db->prepare(
            'UPDATE host_orders SET vatly_id = ?, status = ?, invoice_number = ?, '
            . 'total = ?, currency = ?, payment_method = ?, testmode = ? '
            . 'WHERE id = ? AND vatly_id IS NULL AND testmode = ?'
        );
        $query->execute([
            $data->vatlyId, $data->status, $data->invoiceNumber,
            $data->total, $data->currency, $data->paymentMethod,
            (int) $data->testmode, $hostId, (int) $data->testmode,
        ]);
        return $this->findByVatlyId($data->vatlyId);
    }

    public function update(OrderInterface $order, UpdateOrderData $data): OrderInterface
    {
        $query = $this->db->prepare(
            'UPDATE host_orders SET status = ?, invoice_number = ?, total = ?, '
            . 'currency = ?, payment_method = ? WHERE vatly_id = ?'
        );
        $query->execute([
            $data->status ?? $order->getStatus(),
            $data->invoiceNumber ?? $order->getInvoiceNumber(),
            $data->total ?? $order->getTotal(),
            $data->currency ?? $order->getCurrency(),
            $data->paymentMethod ?? $order->getPaymentMethod(),
            $order->getVatlyId(),
        ]);
        return $this->findByVatlyId($order->getVatlyId())
            ?? throw new RuntimeException('Host order disappeared during update');
    }
}
```

`store()` here means attach the Vatly ID and confirm an existing row, not insert. Once attached, the built-in reaction finds that ID and calls `update()` on redelivery. The wrapper includes every current `OrderInterface` method, including `isTestmode()`.

This minimal adapter stores order summary fields only. Extend your writer to save subtotal, tax summary, metadata, cancellation reason, and lines if your host needs them. Do not overwrite fields when an update DTO leaves them `null`. Use [Webhooks](Webhooks.md) to make attachment, audit, and fulfillment atomic. Missing routing data may return `null`; database failures must throw.

## Route renewals separately

An initial payment often points to a host order already created by checkout. A renewal may need a new host order. Confirm the initial row when its metadata ID matches. Otherwise, resolve the host customer from `$data->hostCustomerId` and use your host's renewal service only when you can establish the subscription and payment relationship.

Missing metadata alone is not proof of a renewal. It can also mean an anonymous payment, a deleted order, or another integration. Check storefront, mode, and ownership; return `null` or send the event for reconciliation when it cannot be routed. Give renewal creation a unique Vatly order ID so retries cannot insert twice.

Use the same pattern for subscriptions: a wrapper implementing `SubscriptionInterface`, a unique Vatly ID, and a repository that applies partial updates and explicit clear flags. See [Core concepts](CoreConcepts.md) and [Event flow](webhook-flow.md). Full payload fields are in the [API reference](https://docs.vatly.com/api-reference).
