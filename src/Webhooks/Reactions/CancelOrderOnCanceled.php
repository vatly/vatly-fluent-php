<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks\Reactions;

use Vatly\Fluent\Contracts\OrderRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Data\UpdateOrderData;
use Vatly\Fluent\Events\OrderCanceled;

/**
 * Mirrors an order cancellation from Vatly onto the local order row.
 *
 * Find-or-skip: a cancellation for an order we never recorded is a no-op
 * (we don't fabricate a canceled row from nothing). The status is taken
 * verbatim from the event — fluent mirrors Vatly's status vocabulary rather
 * than synthesizing its own.
 *
 * @immutable
 */
class CancelOrderOnCanceled implements WebhookReactionInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
    ) {}

    public function supports(object $event): bool
    {
        return $event instanceof OrderCanceled;
    }

    public function handle(object $event): void
    {
        if (! $event instanceof OrderCanceled) {
            return;
        }

        $order = $this->orders->findByVatlyId($event->orderId);

        if ($order === null) {
            return;
        }

        $this->orders->update($order, new UpdateOrderData(
            status: $event->status,
        ));
    }
}
