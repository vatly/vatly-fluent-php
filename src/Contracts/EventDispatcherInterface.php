<?php

declare(strict_types=1);

namespace Vatly\Contracts;

/**
 * Interface for dispatching events.
 *
 * Framework adapters should implement this to bridge
 * to their native event system.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event.
     */
    public function dispatch(object $event): void;
}
