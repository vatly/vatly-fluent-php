# Customers

Create a Vatly customer for the account that pays, then save the link to your own ID. Here `$vatly` has a `CustomerBindingRepository` wired in, and `user_42` is an authenticated account in your app:

```php
use Vatly\Fluent\CustomerProfile;

$customer = $vatly->customers()->findByHostCustomerId('user_42');
if ($customer === null) {
    $customer = $vatly->customers()->createFor(
        hostCustomerId: 'user_42',
        profile: new CustomerProfile(email: 'buyer@example.com', name: 'Alex'),
        additionalPayload: ['locale' => 'nl_NL'],
    );
}
$profile = new CustomerProfile(vatlyId: $customer->id);
```

Use `$profile` in a [checkout](Checkouts.md). `createFor()` always creates a new remote customer and then calls your binding repository. It throws if that host ID is already bound. Serialize concurrent creation for the same host account; a lookup followed by create is not an atomic lock. Remote creation and your database write are not one transaction. Reconcile the remote ID if binding fails rather than blindly creating again.

## Link an existing customer

After checking ownership, call `attribute($vatlyCustomerId, $hostCustomerId)`. Repeating the same pair is safe. Linking a host already bound to another customer raises `CustomerAlreadyBoundException`. Your repository must also prevent one remote customer from being assigned to two host accounts.

For anonymous checkout, use `new CustomerProfile()`. After payment you can call `claimCustomerFromCheckout($checkoutId, $hostCustomerId)`: it returns `false` if no customer can be resolved yet. Only claim checkouts your server recorded for this user or session. A checkout ID supplied by a browser is not proof of ownership. Claiming the binding does not reassign earlier anonymous order rows; your driver must do that if needed.

## Update account identity

This uses the same authorized customer from above:

```php
use Vatly\Fluent\Data\UpdateCustomerData;

$handle = $vatly->customer($customer->id);
$handle->update(new UpdateCustomerData(name: 'Alex Example'));
$identity = $handle->identity();
```

Identity updates cover name and email. To clear a name explicitly, pass `['name' => null]` instead of a DTO. Billing addresses, company details, and VAT numbers go through the hosted [billing-update flow](Subscriptions.md#update-billing).

`findByEmail()` returns a collection, not a unique match. `findOneByEmail()` returns the first match or `null`. Email search is a recovery tool, not authorization or an automatic binding rule.

Next: [Customer portal](CustomerPortal.md). For field details, see the [API reference](https://docs.vatly.com/api-reference).
