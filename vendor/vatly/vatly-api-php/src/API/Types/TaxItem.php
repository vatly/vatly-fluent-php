<?php

namespace Vatly\API\Types;

class TaxItem
{
    public string $name;
    public float $percentage;
    public ?Money $amount;

    public function __construct(string $name, float $percentage, ?Money $amount)
    {
        $this->name = $name;
        $this->percentage = $percentage;
        $this->amount = $amount;
    }

    public static function createResourceFromApiResult($value): TaxItem
    {
        if (is_array($value)) {
            $value = (object) $value;
        }

        return new TaxItem(
            $value->name,
            $value->percentage,
            $value->amount ? Money::createResourceFromApiResult($value->amount) : null
        );
    }
}
