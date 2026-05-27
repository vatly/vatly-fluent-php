<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

use Vatly\Fluent\Contracts\EventDispatcherInterface;

/**
 * No-op {@see EventDispatcherInterface}.
 *
 * Useful in tests and for api-only consumers that don't need to react to
 * webhook events.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        //
    }
}
