# Cannot customize owner model

When the class you want to bill (User, Organization, Tenant, …) can't be modified to implement [`BillableInterface`](../../src/Contracts/BillableInterface.php) directly — vendor package, sealed domain model, third-party identity provider, immutable DTO — wrap it with an adapter.

## The pattern

The adapter implements `BillableInterface` and holds a reference to the underlying owner. All Vatly-specific state lives outside the wrapped class.

```php
namespace Acme\VatlyBridge;

use Vatly\Fluent\Contracts\BillableInterface;
use Vendor\Sealed\User;  // can't be modified

final class VatlyBillableAdapter implements BillableInterface
{
    public function __construct(
        private readonly User $user,
        private readonly VatlyIdStore $store,   // your read/write for vatly_id
    ) {}

    public function getVatlyId(): ?string         { return $this->store->get($this->user); }
    public function setVatlyId(string $id): void  { $this->store->set($this->user, $id); }
    public function hasVatlyId(): bool            { return $this->getVatlyId() !== null; }
    public function getVatlyEmail(): ?string      { return $this->user->emailAddress(); }
    public function getVatlyName(): ?string       { return $this->user->displayName(); }
    public function getKey()                      { return $this->user->id(); }
    public function save()                        { /* no-op: store.set already persists */ }

    /** Escape hatch — let consumers get the underlying object back. */
    public function unwrap(): User                { return $this->user; }
}
```

`getKey()` returns whatever serves as the owner's identifier (used for polymorphic-owner lookups when persisting subscriptions/orders). `save()` is called by fluent after `setVatlyId()` — implement it if your storage needs an explicit flush, or leave it a no-op when the store wrote immediately.

## Where the `vatly_id` lives

Pick one of the two based on what you control.

### Option A — add a column to the existing table

When you can run migrations against the owner's table but can't change the class. Most ORMs let you read/write columns without code changes (Eloquent's `$user->vatly_id`, Doctrine via embeddables or a second mapping). `VatlyIdStore` becomes a thin wrapper around that column.

```php
final class VatlyIdStore
{
    public function __construct(private Connection $db) {}

    public function get(User $user): ?string
    {
        return $this->db->scalar('SELECT vatly_id FROM users WHERE id = ?', [$user->id()]);
    }

    public function set(User $user, string $vatlyId): void
    {
        $this->db->execute('UPDATE users SET vatly_id = ? WHERE id = ?', [$vatlyId, $user->id()]);
    }
}
```

### Option B — use a join table

When you can't touch the original schema at all. Generalizes naturally to multi-tenant setups where the same Vatly account spans multiple owner types.

```sql
CREATE TABLE vatly_billable_links (
    owner_type   VARCHAR(255) NOT NULL,
    owner_id     VARCHAR(255) NOT NULL,
    vatly_id     VARCHAR(255) NOT NULL UNIQUE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_type, owner_id)
);
```

```php
final class VatlyIdStore
{
    public function __construct(private Connection $db) {}

    public function get(object $owner): ?string
    {
        return $this->db->scalar(
            'SELECT vatly_id FROM vatly_billable_links WHERE owner_type = ? AND owner_id = ?',
            [get_class($owner), $owner->id()],
        );
    }

    public function set(object $owner, string $vatlyId): void
    {
        $this->db->execute(
            'INSERT INTO vatly_billable_links (owner_type, owner_id, vatly_id) VALUES (?, ?, ?)
             ON CONFLICT (owner_type, owner_id) DO UPDATE SET vatly_id = excluded.vatly_id',
            [get_class($owner), $owner->id(), $vatlyId],
        );
    }

    /**
     * Reverse lookup: which owner does this vatly_id point at?
     */
    public function lookupOwner(string $vatlyId): ?array
    {
        $row = $this->db->row(
            'SELECT owner_type, owner_id FROM vatly_billable_links WHERE vatly_id = ?',
            [$vatlyId],
        );
        return $row ?: null;
    }
}
```

## Customer repository

Make your [`CustomerRepositoryInterface`](../../src/Contracts/CustomerRepositoryInterface.php) implementation return adapters, not raw owners.

```php
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\CustomerRepositoryInterface;
use Vatly\Fluent\Exceptions\InvalidCustomerException;

final class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private VendorUserRepository $users,
        private VatlyIdStore $store,
    ) {}

    public function findByVatlyId(string $vatlyId): ?BillableInterface
    {
        $link = $this->store->lookupOwner($vatlyId);    // Option B
        if ($link === null) return null;

        $user = $this->users->find($link['owner_id']);
        return $user ? new VatlyBillableAdapter($user, $this->store) : null;
    }

    public function findByVatlyIdOrFail(string $vatlyId): BillableInterface
    {
        $billable = $this->findByVatlyId($vatlyId);
        if ($billable === null) {
            throw InvalidCustomerException::notFound($vatlyId);
        }
        return $billable;
    }

    public function save(BillableInterface $billable): void
    {
        // VatlyBillableAdapter::setVatlyId already pushed to the store
        // when the link was first created. Nothing else needed unless
        // your domain has post-link side effects (audit log, etc.).
    }
}
```

## Consumer side

When you build the per-owner orchestrator, hand fluent the adapter — not the raw owner:

```php
$adapter = new VatlyBillableAdapter($user, $vatlyIdStore);

$billable = $vatly->billable($adapter);
$billable->subscribe()->toPlan('plan_premium')->create();
```

When you receive a `BillableInterface` back from fluent (e.g. inside a webhook reaction, or via `SubscriptionInterface::getOwner()`), call `unwrap()` to get the underlying owner back for your own domain code:

```php
Event::listen(\Vatly\Fluent\Events\OrderPaid::class, function ($event) use ($customers) {
    $billable = $customers->findByVatlyIdOrFail($event->customerId);

    if ($billable instanceof VatlyBillableAdapter) {
        $user = $billable->unwrap();
        // ... send receipt to $user->emailAddress(), etc.
    }
});
```

## Notes

- **The adapter should be cheap to construct.** Build a fresh one per request / per Vatly call. Don't try to cache them — adapters are value-object-like wrappers, not entities.
- **Don't put domain logic on the adapter.** It exists to satisfy `BillableInterface` and nothing else. Domain code stays on the underlying owner.
- **`getKey()` is used as the polymorphic-owner identifier** when fluent's reactions persist subscriptions / orders. It must be stable and unique across the lifetime of the owner. If your underlying class doesn't expose one, derive it from whatever you already use as a primary key.
- **The adapter pattern also works when the owner *can* be modified** but you want to keep all Vatly concerns physically separated from your domain model. Useful if you want to ship the integration as its own bounded context.
