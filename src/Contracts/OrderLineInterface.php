<?php

declare(strict_types=1);

namespace Vatly\Fluent\Contracts;

/**
 * Interface for order line entities.
 *
 * Mirrors {@see RefundInterface}: a thin read surface over the driver's own
 * persisted order-line row, populated from Vatly's `order.paid` webhook.
 *
 * The order↔subscription link lives here, at the line level, as a generic
 * (`productType`, `productId`) pair — a line links to a subscription when
 * `getProductType() === 'subscription'` → `getProductId()` is the
 * `subscription_…` id. `productType` is the raw API string (no fluent enum)
 * so new backend product types stay queryable without a fluent release.
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
}
