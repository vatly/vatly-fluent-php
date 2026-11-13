<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for order line entities.
 *
 * Mirrors {@see RefundInterface}: a thin read surface over the driver's own
 * persisted order-line row, populated from Vatly's `order.paid` webhook.
 *
 * The order↔subscription link lives here, at the line level, as
 * {@see self::getSubscriptionId()} (Vatly's `relatedResources.subscriptionId`).
 * It names the subscription a line belongs to whatever the line's product:
 * an initial signup line references the plan (`productType` is
 * `subscription_plan`, `productId` the `subscription_plan_…` id), while a
 * renewal or immediate-update line references the subscription itself
 * (`productType` is `subscription`). `productType` is the raw API string (no
 * fluent enum) so new backend product types stay queryable without a fluent
 * release.
 */
interface OrderLineInterface
{
    /**
     * Get the Vatly order-line ID (the `order_item_…` id).
     */
    public function getVatlyId(): string;

    /**
     * Get the Vatly ID of the parent order this line belongs to.
     */
    public function getOrderId(): string;

    /**
     * Get the line description.
     */
    public function getDescription(): string;

    /**
     * Get the line quantity.
     */
    public function getQuantity(): int;

    /**
     * Get the line base price (per-unit, before quantity), in integer cents.
     */
    public function getBasePrice(): int;

    /**
     * Get the line total (gross, including tax), in integer cents.
     */
    public function getTotal(): int;

    /**
     * Get the line subtotal (net of tax), in integer cents.
     */
    public function getSubtotal(): int;

    /**
     * Get the raw Vatly product type this line links to (e.g. "subscription",
     * "one_off_product"), or null when the backend has not (yet) attributed it.
     */
    public function getProductType(): ?string;

    /**
     * Get the linked product's id (e.g. a `subscription_…` id for a
     * subscription line), or null when unattributed.
     */
    public function getProductId(): ?string;

    /**
     * Get the id of the subscription this line belongs to (a `subscription_…`
     * id), whatever its product type, so initial signup lines link to their
     * subscription as well as renewals.
     *
     * Persist it from {@see \Vatly\API\Types\OrderLineData::$relatedResources}
     * as `$data->relatedResources?->subscriptionId`. Null for lines unrelated
     * to a subscription, for lines Vatly created before it carried the link,
     * and for a signup line whose subscription was not yet available.
     */
    public function getSubscriptionId(): ?string;
}
