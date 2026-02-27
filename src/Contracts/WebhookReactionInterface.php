<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for webhook reactions.
 *
 * Reactions handle the core billing logic that should happen
 * when a webhook event is received (e.g., storing an order,
 * creating a subscription). Framework packages provide concrete
 * repository implementations; the reactions themselves are portable.
 */
interface WebhookReactionInterface
{
    /**
     * Whether this reaction should handle the given event.
     */
    public function supports(object $event): bool;

    /**
     * Handle the event.
     */
    public function handle(object $event): void;
}
