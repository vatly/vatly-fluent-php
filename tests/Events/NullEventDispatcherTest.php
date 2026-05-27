<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Events;

use stdClass;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Events\NullEventDispatcher;
use Vatly\Fluent\Tests\TestCase;

class NullEventDispatcherTest extends TestCase
{
    public function test_dispatch_swallows_events_without_side_effects(): void
    {
        $dispatcher = new NullEventDispatcher();

        $dispatcher->dispatch(new stdClass());

        // Reaching here means no exception was thrown — that's the contract.
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }
}
