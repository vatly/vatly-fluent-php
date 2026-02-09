<?php

namespace Vatly\API\Types;

class TaxesCollection
{
    /**
     * @var TaxItem[]
     */
    public array $taxes = [];

    public function __construct(array $taxes)
    {
        foreach ($taxes as $tax) {
            $this->taxes[] = TaxItem::createResourceFromApiResult($tax);
        }
    }

    public static function createResourceFromApiResult(array $value): TaxesCollection
    {
        return new TaxesCollection(
            $value
        );
    }
}
