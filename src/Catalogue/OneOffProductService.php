<?php

declare(strict_types=1);

namespace Vatly\Fluent\Catalogue;

use Vatly\API\Resources\OneOffProduct;
use Vatly\API\Resources\OneOffProductCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Concerns\GuardsApiCalls;

/**
 * Fluent surface for managing one-off products in the Vatly catalogue.
 *
 * Reached via {@see \Vatly\Fluent\Vatly::oneOffProducts()}. Thin, discoverable
 * wrapper over api-php's {@see \Vatly\API\Endpoints\OneOffProductEndpoint},
 * returning the hydrated api-php {@see OneOffProduct} resource (which carries the
 * catalogue-lifecycle fields `taxBehavior`, `productType`, `archivedAt`,
 * `pendingUpdates`, `updateStatus` and the `isArchived()` convenience).
 *
 * Live-mode create/update submissions are held for Vatly review before they take
 * effect; in test mode they are approved automatically. See the api-php endpoint
 * docblocks for the per-operation lifecycle.
 */
class OneOffProductService
{
    use GuardsApiCalls;

    public function __construct(
        /** @readonly */
        private VatlyApiClient $apiClient,
    ) {
        //
    }

    /**
     * Create a one-off product.
     *
     * @param array<string, mixed> $payload  Any of `name`, `description`, `basePrice`,
     *                                        `productType`, `taxBehavior`, `testmode`.
     * @param array<string, mixed> $filters
     */
    public function create(array $payload, array $filters = []): OneOffProduct
    {
        $product = $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->create($payload, $filters));

        assert($product instanceof OneOffProduct);

        return $product;
    }

    /**
     * Fetch a single one-off product by id.
     *
     * @param array<string, mixed> $parameters
     */
    public function find(string $id, array $parameters = []): OneOffProduct
    {
        $product = $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->get($id, $parameters));

        assert($product instanceof OneOffProduct);

        return $product;
    }

    /**
     * Submit an update to a one-off product. Each request is the complete set of
     * changes relative to the current live product. In live mode the change is
     * held as a pending update and reviewed by Vatly before it takes effect; in
     * test mode it is applied automatically. The returned product carries
     * `pendingUpdates` and `updateStatus`.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filters
     */
    public function update(string $id, array $data = [], array $filters = []): ?OneOffProduct
    {
        $product = $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->update($id, $data, $filters));

        assert($product === null || $product instanceof OneOffProduct);

        return $product;
    }

    /**
     * Archive a one-off product, taking it out of the sellable catalogue.
     * Archived products are hidden from listings unless `includeArchived` is
     * passed, and refused by new checkouts. Returns nothing (the API replies
     * `204 No Content`).
     *
     * @param array<string, mixed> $filters
     */
    public function archive(string $id, array $filters = []): void
    {
        $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->archive($id, $filters));
    }

    /**
     * Put an archived product back on sale. Returns the product, now on sale again.
     *
     * @param array<string, mixed> $filters
     */
    public function unarchive(string $id, array $filters = []): ?OneOffProduct
    {
        $product = $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->unarchive($id, $filters));

        assert($product === null || $product instanceof OneOffProduct);

        return $product;
    }

    /**
     * List one-off products (a single page). Pass `['includeArchived' => true]`
     * in `$parameters` to include archived products.
     *
     * @param array<string, mixed> $parameters
     */
    public function list(
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $parameters = [],
    ): OneOffProductCollection {
        $collection = $this->guardApiCall(fn () => $this->apiClient->oneOffProducts->page($startingAfter, $endingBefore, $limit, $parameters));

        assert($collection instanceof OneOffProductCollection);

        return $collection;
    }
}
