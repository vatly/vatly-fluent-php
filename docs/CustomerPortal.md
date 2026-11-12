# Customer portal

Let a customer manage billing on Vatly's hosted portal instead of building those screens yourself. This works in API-only mode. Pass an authorized customer ID, never an unchecked ID from a query string:

```php
use Vatly\Fluent\Vatly;

function customerPortal(Vatly $vatly, string $customerId): string
{
    $session = $vatly->customer($customerId)->portalSession([
        'returnUrl' => 'https://app.example.com/account',
    ]);

    return $session->url;
}
```

Redirect the browser to the returned URL. Create a fresh session when the signed-in customer clicks your billing button. The session URL is short-lived and single-use; do not cache it, log it, email it, or share it between users. The result also exposes `expiresAt` and `returnUrl`.

The portal provides hosted invoice history and billing controls. Your app must still receive [Webhooks](Webhooks.md) to update local subscriptions when a customer makes a change there. A return to your app does not confirm that anything changed.

For a focused address or VAT update on one subscription, use [updateBilling()](Subscriptions.md#update-billing). For session fields and available controls, see the [API reference](https://docs.vatly.com/api-reference).
