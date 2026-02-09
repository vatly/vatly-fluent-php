<?php

declare(strict_types=1);

namespace Vatly\Actions;

use Vatly\Actions\Responses\GetCheckoutResponse;

class GetCheckout extends BaseAction
{
    public function execute(string $checkoutId, array $parameters = []): GetCheckoutResponse
    {
        $apiResponse = $this->vatlyApiClient->checkouts->get($checkoutId, $parameters);

        return GetCheckoutResponse::fromApiResponse($apiResponse);
    }
}
