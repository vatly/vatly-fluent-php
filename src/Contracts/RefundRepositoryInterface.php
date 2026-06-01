<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Full refund repository — both read and write sides.
 *
 * Supplying this to {@see \Vatly\Fluent\Wiring} is optional: when present the
 * built-in {@see \Vatly\Fluent\Webhooks\Reactions\SyncRefundOnStatusChange}
 * reaction persists `refund.*` webhooks; when absent the typed refund events
 * are still dispatched for drivers to handle themselves.
 */
interface RefundRepositoryInterface extends RefundReader, RefundWriter
{
    //
}
