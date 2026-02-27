<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions\Responses;

use Vatly\API\Resources\Subscription;

/**
 * Response from getting a subscription.
 */
final class GetSubscriptionResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?string $planId = null,
        public readonly ?string $name = null,
        public readonly ?int $quantity = null,
        public readonly ?string $status = null,
        public readonly ?string $cancelledAt = null,
        public readonly ?string $endedAt = null,
        public readonly ?string $trialEndAt = null,
    ) {
        //
    }

    public static function fromApiResponse(Subscription $response): static
    {
        return new static(
            subscriptionId: $response->id,
            planId: $response->subscriptionPlanId ?? null,
            name: $response->name ?? null,
            quantity: $response->quantity ?? null,
            status: $response->status ?? null,
            cancelledAt: $response->cancelledAt ?? null,
            endedAt: $response->endedAt ?? null,
            trialEndAt: $response->trialEndAt ?? null,
        );
    }
}
