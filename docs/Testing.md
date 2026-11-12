# Testing

Test your application's checkout decisions without making a request to Vatly. Inject `FakeVatly` where your application expects `Vatly`, then call your real checkout handler with it.

```php
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Testing\FakeCheckout;
use Vatly\Fluent\Testing\FakeVatly;

$fake = (new FakeVatly())->onSubscriptionCreate(
    fn (string $planId) => FakeCheckout::make('https://checkout.vatly.test/example'),
);
$checkout = $fake->subscriptionBuilder(new CustomerProfile(vatlyId: 'customer_example'))
    ->toPlan('subscription_plan_pro')
    ->withQuantity(3)
    ->create();

\PHPUnit\Framework\Assert::assertSame(
    'https://checkout.vatly.test/example',
    $checkout->links->checkoutUrl->href,
);
$fake->assertSubscriptionCreated('subscription_plan_pro');
$fake->assertNothingCanceled();
```

Install PHPUnit as a development dependency to use the `assert*` helpers. `withDefaultCheckout()` sets a shared result; `onCheckoutCreate()` scripts one-off checkout results. The fake also records swaps and cancellations routed through its subscription handles.

Fakes test your application branches, not API validation, signature checking, or database behavior. Only use the fake surfaces you have scripted; it is not a sandbox for every SDK operation.

## Test your webhook receiver

Use a local fixture and an independent HMAC signature. This sends no request to Vatly:

```php
$secret = 'local-fixture-secret';
$payload = json_encode([
    'id' => 'event_fixture',
    'resource' => 'webhook_event',
    'eventName' => 'webhook.setup',
    'entityType' => 'webhook_endpoint',
    'entityId' => 'webhook_endpoint_fixture',
    'testmode' => true,
    'createdAt' => '2026-01-01T00:00:00Z',
    'object' => ['id' => 'webhook_endpoint_fixture'],
], JSON_THROW_ON_ERROR);
$timestamp = time();
$signature = 't=' . $timestamp . ',v1='
    . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

$verifier = new \Vatly\Fluent\Webhooks\SignatureVerifier();
\PHPUnit\Framework\Assert::assertTrue($verifier->isValid($signature, $payload, $secret));
\PHPUnit\Framework\Assert::assertFalse($verifier->isValid($signature, $payload . ' ', $secret));
```

Configure your test processor with that secret and pass the raw body and header through your HTTP handler. Assert a valid delivery is committed, an invalid signature writes nothing, and redelivery does not fulfill twice. Force a repository error after the audit write and prove the whole transaction rolls back. Test two concurrent deliveries against your real database, not just an in-memory repository.

For subscription access, cover a future grace end, a past end, resume clearing the date, and nonpayment cancellation. For orders, cover initial payment, renewal, duplicate delivery, and a reversal without changing `paid` to a made-up status.

## Exercise the Vatly sandbox

With a test key, `testHelpers()->advanceRenewal($id)`, `forceRenewalPaid($id)`, and `forceRenewalFailed($id, 'card_expired')` advance real test-mode state and provoke webhooks. Unlike the fakes above, these make remote writes. Use them only in a dedicated sandbox integration test, never with live records.

For sandbox fields, see the [API reference](https://docs.vatly.com/api-reference). For receiver design, see [Webhooks](Webhooks.md).
