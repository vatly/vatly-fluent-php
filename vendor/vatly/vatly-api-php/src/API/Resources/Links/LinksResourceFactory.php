<?php

namespace Vatly\API\Resources\Links;

use Vatly\API\Types\Link;

class LinksResourceFactory
{
    public static function createResourceFromApiResult($apiResult, BaseLinksResource $resource): BaseLinksResource
    {
        if (is_array($apiResult)) {
            $apiResult = (object) $apiResult;
        }

        if (! is_object($apiResult)) {
            throw new \InvalidArgumentException('Invalid API result');
        }

        foreach ($apiResult as $property => $value) {
            if (is_array($value)) {
                $value = (object) $value;
            }
            if ($value === null) {
                $resource->{$property} = null;
            } else {
                $resource->{$property} = new Link($value->href, $value->type);
            }
        }

        return $resource;
    }
}
