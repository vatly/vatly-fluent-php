<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\Responses\GetCheckoutResponse;

class GetCheckout extends BaseAction
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $checkoutId, array $parameters = []): GetCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->get($checkoutId, $parameters);

        assert($apiResponse instanceof Checkout);

        return GetCheckoutResponse::fromApiResponse($apiResponse);
    }
}
