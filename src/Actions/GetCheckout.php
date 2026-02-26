<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\Fluent\Actions\Responses\GetCheckoutResponse;

class GetCheckout extends BaseAction
{
    public function execute(string $checkoutId, array $parameters = []): GetCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->get($checkoutId, $parameters);

        return GetCheckoutResponse::fromApiResponse($apiResponse);
    }
}
