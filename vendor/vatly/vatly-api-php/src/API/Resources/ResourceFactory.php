<?php

declare(strict_types=1);

namespace Vatly\API\Resources;

use ReflectionNamedType;
use ReflectionProperty;
use Vatly\API\Resources\Links\BaseLinksResource;
use Vatly\API\Resources\Links\LinksResourceFactory;
use Vatly\API\Types\Address;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxesCollection;
use Vatly\API\VatlyApiClient;

#[\AllowDynamicProperties]
class ResourceFactory
{
    /**
     * Create resource object from Api result
     *
     * @param object $apiResult
     * @param BaseResource $resource
     *
     * @return BaseResource
     */
    public static function createResourceFromApiResult(object $apiResult, BaseResource $resource): BaseResource
    {
        foreach ($apiResult as $property => $value) {
            switch ($property) {
                case 'links':
                    try {
                        $rp = new ReflectionProperty(get_class($resource), 'links');
                        $rpType = $rp->getType();
                        if ($rpType instanceof ReflectionNamedType) {
                            $linksClass = $rpType->getName();
                        } else {
                            $linksClass = BaseLinksResource::class;
                        }
                    } catch (\ReflectionException $e) {
                        $linksClass = BaseLinksResource::class;
                    }

                    $resource->{$property} = LinksResourceFactory::createResourceFromApiResult($value, new $linksClass);

                    break;

                case 'customerDetails':
                case 'merchantDetails':
                case 'billingAddress':
                case 'shippingAddress':
                    $resource->{$property} = Address::createResourceFromApiResult($value);

                    break;

                case 'price':
                case 'basePrice':
                case 'taxAmount':
                case 'amount':
                case 'settlementAmount':
                case 'total':
                case 'subtotal':
                    $resource->{$property} = Money::createResourceFromApiResult($value);

                    break;

                case 'taxes':
                    $resource->{$property} = TaxesCollection::createResourceFromApiResult($value);

                    break;

                default:
                    $resource->{$property} = $value;

                    break;
            }
        }

        return $resource;
    }

    /**
     * @param \Vatly\API\VatlyApiClient $client
     * @param array $input
     * @param string $resourceClass
     * @param object|null $links
     * @param string|null $resourcePageClass
     * @return mixed
     */
    public static function createCursorResourceCollection(
        VatlyApiClient $client,
        array $input,
        string $resourceClass,
        ?object $links = null,
        ?string $resourcePageClass = null
    ) {
        if (null === $resourcePageClass) {
            $resourcePageClass = $resourceClass.'Collection';
        }

        $data = new $resourcePageClass($client, count($input), $links);
        foreach ($input as $item) {
            $data[] = static::createResourceFromApiResult($item, new $resourceClass($client));
        }

        return $data;
    }
}
