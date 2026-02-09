<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

use Vatly\API\Resources\Subscription;

/**
 * Response from swapping a subscription plan.
 */
class SwapSubscriptionPlanResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $subscriptionPlanId,
        public readonly int $quantity,
    ) {
        //
    }

    public static function fromApiResponse(Subscription $response): static
    {
        return new static(
            subscriptionId: $response->id,
            subscriptionPlanId: $response->subscriptionPlanId,
            quantity: $response->quantity,
        );
    }
}
