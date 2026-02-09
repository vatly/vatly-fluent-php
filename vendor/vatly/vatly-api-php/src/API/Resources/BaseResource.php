<?php

declare(strict_types=1);

namespace Vatly\API\Resources;

use Vatly\API\Resources\Links\BaseLinksResource;
use Vatly\API\VatlyApiClient;

/**
 * Class BaseResource
 *
 * @property BaseLinksResource $links
 */

#[\AllowDynamicProperties]
abstract class BaseResource
{
    /**
     * @var \Vatly\API\VatlyApiClient
     */
    protected VatlyApiClient $apiClient;

    public string $id;

    public function __construct(VatlyApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }
}
