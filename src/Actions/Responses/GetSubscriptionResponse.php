<?php

declare(strict_types=1);

namespace Vatly\Actions\Responses;

use Vatly\API\Resources\Subscription;

/**
 * Response from getting a subscription.
 */
class GetSubscriptionResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?string $planId = null,
        public readonly ?int $quantity = null,
        public readonly ?string $status = null,
    ) {
        //
    }

    public static function fromApiResponse(Subscription $response): static
    {
        return new static(
            subscriptionId: $response->id,
            planId: $response->subscriptionPlanId ?? null,
            quantity: $response->quantity ?? null,
            status: $response->status ?? null,
        );
    }
}
