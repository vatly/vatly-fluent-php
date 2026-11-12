# Webhooks

Keep local billing records current after checkout, renewals, and portal changes. Read the raw request body and the `Vatly-Signature` header, then pass both unchanged to Fluent. This receiver function uses the fully wired instance from [Core concepts](CoreConcepts.md):

```php
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Vatly;

function receiveWebhook(Vatly $vatly, string $rawBody, string $signature): int
{
    try {
        $vatly->webhookProcessor()->handle($rawBody, $signature);
        return 201;
    } catch (InvalidWebhookSignatureException) {
        return 403;
    }
}
```

Use the returned integer as your HTTP response status. Other failures must produce a non-2xx response so Vatly can retry. Register your public HTTPS receiver in Vatly, set its signing secret in your configuration, and exempt only that route from browser CSRF checks. Never disable signature verification.

## Verify the exact bytes

The header format is `t=<unix_seconds>,v1=<hex_hmac_sha256>`. The signed message is the timestamp, a dot, and the raw body. The SDK checks the signature and timestamp replay window, then validates the envelope. Do not decode and re-encode JSON before verification. Use the endpoint's signing secret, not your API key.

Valid deliveries are recorded, mapped to typed events, passed through built-in reactions, then dispatched to your event bus. The signed resource snapshot supplies the data; built-in reactions do not make enrichment API calls. See [Event flow](webhook-flow.md) for the storage mapping.

## Make retries transactional

The simple receiver above does not guarantee idempotency. Fluent's audit repository is not a processed-event ledger, and listeners can run again on redelivery. Use a durable event claim in the same database transaction as the audit log, billing writes, and outbox writes.

Here is a complete SQLite transaction boundary. Create this table once in your application's migrations:

```sql
CREATE TABLE processed_vatly_events (
    event_id TEXT PRIMARY KEY
);
```

Use a PDO connection in exception mode. Every repository and your outbox dispatcher must share this connection and must not commit independently. Supply the same `$secret` that is configured on `$vatly`:

```php
use Vatly\API\Exceptions\InvalidSignatureException;
use Vatly\API\Webhooks\Webhook;
use Vatly\Fluent\Vatly;

function receiveOnce(PDO $db, Vatly $vatly, string $body, string $signature, string $secret): int
{
    try {
        $verified = Webhook::parse($body, $signature, $secret);
    } catch (InvalidSignatureException | InvalidArgumentException) {
        return 403;
    }

    $db->beginTransaction();
    try {
        $claim = $db->prepare('INSERT OR IGNORE INTO processed_vatly_events (event_id) VALUES (?)');
        $claim->execute([$verified->id]);
        if ($claim->rowCount() === 0) {
            $db->commit();
            return 201;
        }

        $vatly->webhookProcessor()->handle($body, $signature);
        $db->commit();
        return 201;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
```

The second parse inside Fluent is intentional: the first verifies the event ID before deduplication; the processor still verifies every delivery it handles. Adapt the insert to your database's conflict syntax. A lock timeout or deadlock must fail the request and retry, not return success.

Queue emails, provisioning, and other external effects through an outbox committed with the billing writes. Do not send an email inside the transaction: a rollback cannot unsend it. Make outbox consumers idempotent too. Event-ID deduplication handles redelivery of one event; unique entity IDs and idempotent fulfillment rules also protect against different events for the same payment.

Deliveries can arrive out of order. Store-or-update does not prevent an older snapshot overwriting a newer one, and find-or-skip events cannot repair a missing row. Define reconciliation and stale-event handling in your driver. Persist test mode and keep test deliveries away from live access rules.

## Add application reactions

Pass `WebhookReactionInterface` implementations in `Wiring(additionalWebhookReactions: [...])`. They run after built-ins and before the final event dispatch. Typed webhook events live in `Vatly\API\Webhooks\Events`; local-row-created events live in `Vatly\Fluent\Events`.

Use [Testing](Testing.md) to test signatures, rollback, and retries. For payload fields, see the [API reference](https://docs.vatly.com/api-reference).
