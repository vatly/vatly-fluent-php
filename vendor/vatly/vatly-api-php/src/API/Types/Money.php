<?php

namespace Vatly\API\Types;

class Money
{
    /**
     * @example "EUR"
     */
    public string $currency;

    /**
     * @example "100.00"
     */
    public string $value;

    public function __construct(string $currency, string $value)
    {
        $this->currency = $currency;
        $this->value = $value;
    }

    public static function createResourceFromApiResult($value): Money
    {
        if (is_array($value)) {
            $value = (object) $value;
        }

        return new self($value->currency, $value->value);
    }
}
