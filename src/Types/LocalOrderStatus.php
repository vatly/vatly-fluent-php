<?php

declare(strict_types=1);

namespace Vatly\Fluent\Types;

/**
 * Consumer-local order statuses synthesized from financial webhooks.
 *
 * Vatly's public API intentionally collapses an order's refund/chargeback
 * lifecycle back to `paid` — the hosted product surfaces a single "paid"
 * state and never returns `refunded`, `partially_refunded`, or `chargeback`
 * on the Order resource. Consumers that want a richer local picture can
 * derive it from the `refund.*` / `order.chargeback_*` webhook payloads,
 * which carry the original order id plus the (sub)totals needed to compute
 * the new state without an API re-fetch.
 *
 * The string values mirror Vatly's *internal* order status vocabulary so a
 * driver's local rows line up with what an operator sees inside Vatly's
 * dashboard. These are written by {@see \Vatly\Fluent\Webhooks\Reactions\SyncOrderOnRefundChange}
 * (and the chargeback equivalent) — they are never produced by enriching an
 * order via `GetOrder`, which always reports `paid`.
 */
final class LocalOrderStatus
{
    public const PAID = 'paid';

    public const REFUNDED = 'refunded';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public const CHARGEBACK = 'chargeback';
}
