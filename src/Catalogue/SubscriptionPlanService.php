<?php

declare(strict_types=1);

namespace Vatly\Fluent\Catalogue;

use Vatly\API\Resources\SubscriptionPlan;
use Vatly\API\Resources\SubscriptionPlanCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Concerns\GuardsApiCalls;

/**
 * Fluent surface for managing subscription plans in the Vatly catalogue.
 *
 * Reached via {@see \Vatly\Fluent\Vatly::subscriptionPlans()}. Thin, discoverable
 * wrapper over api-php's {@see \Vatly\API\Endpoints\SubscriptionPlanEndpoint},
 * returning the hydrated api-php {@see SubscriptionPlan} resource (which carries
 * the catalogue-lifecycle fields `taxBehavior`, `productType`, `archivedAt`,
 * `pendingUpdates`, `updateStatus` and the `isArchived()` convenience).
 *
 * Live-mode create/update submissions are held for Vatly review before they take
 * effect; in test mode they are approved automatically. See the api-php endpoint
 * docblocks for the per-operation lifecycle.
 */
class SubscriptionPlanService
{
    use GuardsApiCalls;

    public function __construct(
        /** @readonly */
        private VatlyApiClient $apiClient,
    ) {
        //
    }

    /**
     * Create a subscription plan.
     *
     * @param array<string, mixed> $payload  Any of `name`, `description`, `basePrice`,
     *                                        `productType`, `interval`, `intervalCount`,
     *                                        `taxBehavior`, `testmode`.
     * @param array<string, mixed> $filters
     */
    public function create(array $payload, array $filters = []): SubscriptionPlan
    {
        $plan = $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->create($payload, $filters));

        assert($plan instanceof SubscriptionPlan);

        return $plan;
    }

    /**
     * Fetch a single subscription plan by id.
     *
     * @param array<string, mixed> $parameters
     */
    public function find(string $id, array $parameters = []): SubscriptionPlan
    {
        $plan = $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->get($id, $parameters));

        assert($plan instanceof SubscriptionPlan);

        return $plan;
    }

    /**
     * Submit an update to a subscription plan. Each request is the complete set
     * of changes relative to the current live plan. In live mode the change is
     * held as a pending update and reviewed by Vatly before it takes effect; in
     * test mode it is applied automatically. The interval cannot be changed once
     * the plan has ever been used by a subscription; the price stays changeable.
     * The returned plan carries `pendingUpdates` and `updateStatus`.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filters
     */
    public function update(string $id, array $data = [], array $filters = []): ?SubscriptionPlan
    {
        $plan = $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->update($id, $data, $filters));

        assert($plan === null || $plan instanceof SubscriptionPlan);

        return $plan;
    }

    /**
     * Archive a subscription plan, closing it to new business. Subscribers
     * already on the plan keep billing unchanged. Returns nothing (the API
     * replies `204 No Content`).
     *
     * @param array<string, mixed> $filters
     */
    public function archive(string $id, array $filters = []): void
    {
        $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->archive($id, $filters));
    }

    /**
     * Re-open an archived plan to new business. Returns the plan, now open again.
     *
     * @param array<string, mixed> $filters
     */
    public function unarchive(string $id, array $filters = []): ?SubscriptionPlan
    {
        $plan = $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->unarchive($id, $filters));

        assert($plan === null || $plan instanceof SubscriptionPlan);

        return $plan;
    }

    /**
     * List subscription plans (a single page). Pass `['includeArchived' => true]`
     * in `$parameters` to include archived plans.
     *
     * @param array<string, mixed> $parameters
     */
    public function list(
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $parameters = [],
    ): SubscriptionPlanCollection {
        $collection = $this->guardApiCall(fn () => $this->apiClient->subscriptionPlans->page($startingAfter, $endingBefore, $limit, $parameters));

        assert($collection instanceof SubscriptionPlanCollection);

        return $collection;
    }
}
