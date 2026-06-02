<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Full chargeback repository — both read and write sides.
 *
 * Supplying this to {@see \Vatly\Fluent\Wiring} is optional: when present the
 * built-in {@see \Vatly\Fluent\Webhooks\Reactions\SyncChargebackOnStatusChange}
 * reaction persists `order.chargeback_*` webhooks; when absent the typed
 * chargeback events are still dispatched for drivers to handle themselves.
 */
interface ChargebackRepositoryInterface extends ChargebackReader, ChargebackWriter
{
    //
}
