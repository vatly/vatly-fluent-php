<?php

declare(strict_types=1);

namespace Vatly\API\Endpoints;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\BaseResource;
use Vatly\API\Resources\BaseResourcePage;
use Vatly\API\Resources\Links\LinksResourceFactory;
use Vatly\API\Resources\Links\PaginationLinks;
use Vatly\API\Resources\ResourceFactory;
use Vatly\API\VatlyApiClient;

abstract class BaseEndpoint
{
    public const REST_CREATE = VatlyApiClient::HTTP_POST;
    public const REST_UPDATE = VatlyApiClient::HTTP_PATCH;
    public const REST_READ = VatlyApiClient::HTTP_GET;
    public const REST_LIST = VatlyApiClient::HTTP_GET;
    public const REST_DELETE = VatlyApiClient::HTTP_DELETE;

    /**
     * @var \Vatly\API\VatlyApiClient
     */
    protected VatlyApiClient $client;

    /**
     * @var string
     */
    protected string $resourcePath;

    /**
     * @var string|null
     */
    protected ?string $parentId;

    /**
     * @param \Vatly\API\VatlyApiClient $api
     */
    public function __construct(VatlyApiClient $api)
    {
        $this->client = $api;
    }

    /**
     * @param array $filters
     * @return string
     */
    protected function buildQueryString(array $filters): string
    {
        if (empty($filters)) {
            return "";
        }

        foreach ($filters as $key => $value) {
            if ($value === true) {
                $filters[$key] = "true";
            }

            if ($value === false) {
                $filters[$key] = "false";
            }
        }

        return "?" . http_build_query($filters, "", "&");
    }

    /**
     * @param array $body
     * @param array $filters
     * @return BaseResource
     * @throws \Vatly\API\Exceptions\ApiException
     */
    protected function rest_create(array $body, array $filters): BaseResource
    {
        $result = $this->client->performHttpCall(
            self::REST_CREATE,
            $this->getResourcePath() . $this->buildQueryString($filters),
            $this->parseRequestBody($body)
        );

        return ResourceFactory::createResourceFromApiResult($result, $this->getResourceObject());
    }

    /**
     * Sends a PATCH request to a single Vatly API object.
     *
     * @param string $id
     * @param array $body
     *
     * @return null|BaseResource
     * @throws \Vatly\API\Exceptions\ApiException
     */
    protected function rest_update(string $id, array $body = []): ?BaseResource
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->client->performHttpCall(
            self::REST_UPDATE,
            "{$this->getResourcePath()}/{$id}",
            $this->parseRequestBody($body)
        );

        if ($result == null) {
            return null;
        }

        return ResourceFactory::createResourceFromApiResult($result, $this->getResourceObject());
    }

    /**
     * Retrieves a single object from the REST API.
     *
     * @param string $id Id of the object to retrieve.
     * @param array $filters
     * @return BaseResource
     * @throws ApiException
     */
    protected function rest_read($id, array $filters): BaseResource
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->client->performHttpCall(
            self::REST_READ,
            "{$this->getResourcePath()}/{$id}" . $this->buildQueryString($filters),
        );

        return ResourceFactory::createResourceFromApiResult($result, $this->getResourceObject());
    }

    /**
     * Sends a DELETE request to a single Molle API object.
     *
     * @param string $id
     * @param array $body
     *
     * @return BaseResource|null
     * @throws ApiException
     */
    protected function rest_delete($id, array $body = []): ?BaseResource
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->client->performHttpCall(
            self::REST_DELETE,
            "{$this->getResourcePath()}/{$id}",
            $this->parseRequestBody($body)
        );

        if ($result == null) {
            return null;
        }

        return ResourceFactory::createResourceFromApiResult($result, $this->getResourceObject());
    }



    /**
     * Get the object that is used by this API endpoint. Every API endpoint uses one type of object.
     *
     * @return BaseResource
     */
    abstract protected function getResourceObject(): BaseResource;

    /**
     * @param string $resourcePath
     */
    public function setResourcePath(string $resourcePath): void
    {
        $this->resourcePath = strtolower($resourcePath);
    }

    /**
     * @return string
     * @throws ApiException
     */
    public function getResourcePath(): string
    {
        if (strpos($this->resourcePath, "_") !== false) {
            [$parentResource, $childResource] = explode("_", $this->resourcePath, 2);

            if (empty($this->parentId)) {
                throw new ApiException("Subresource '{$this->resourcePath}' used without parent '$parentResource' ID.");
            }

            return "$parentResource/{$this->parentId}/$childResource";
        }

        return $this->resourcePath;
    }

    /**
     * @param array $body
     * @return null|string
     */
    protected function parseRequestBody(array $body): ?string
    {
        if (empty($body)) {
            return null;
        }

        return @json_encode($body);
    }

    /**
     * Get the page object that is used by this API endpoint. Every API endpoint uses one type of page object.
     *
     * @param int $count
     * @param PaginationLinks $links
     *
     * @return BaseResourcePage
     */
    abstract protected function getResourcePageObject(int $count, PaginationLinks $links): BaseResourcePage;

    /**
     * Get a page of objects from the REST API.
     *
     * @param string|null $startingAfter
     * @param string|null $endingBefore
     * @param int|null $limit
     * @param array $filters
     *
     * @return BaseResourcePage
     * @throws \Vatly\API\Exceptions\ApiException
     */
    protected function rest_list(
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        ?int $limit = null,
        array $filters = []
    ): BaseResourcePage {
        if ($startingAfter !== null && $endingBefore !== null) {
            throw new ApiException("You can only use one of startingAfter or endingBefore.");
        }

        $apiPath = $this->getResourcePath() . $this->buildQueryString(
            array_merge(
                [
                    "startingAfter" => $startingAfter,
                    "endingBefore" => $endingBefore,
                    "limit" => $limit,
                ],
                $filters
            )
        );

        $result = $this->client->performHttpCall(self::REST_LIST, $apiPath);

        /** @var PaginationLinks $links */
        $links = LinksResourceFactory::createResourceFromApiResult($result->links, new PaginationLinks());

        $collection = $this->getResourcePageObject($result->count, $links);

        foreach ($result->data as $dataResult) {
            $collection[] = ResourceFactory::createResourceFromApiResult(
                $dataResult,
                $this->getResourceObject()
            );
        }

        return $collection;
    }
}
