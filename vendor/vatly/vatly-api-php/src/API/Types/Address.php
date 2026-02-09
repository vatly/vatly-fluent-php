<?php

namespace Vatly\API\Types;

class Address
{
    public string $fullName = '';

    public string $streetAndNumber = '';

    public string $streetAdditional = '';

    public string $postalCode = '';

    public string $city = '';

    public string $region = '';

    public string $countryCode = '';

    public string $country = '';

    public string $companyName = '';

    public string $vatNumber = '';

    public string $email = '';


    /**
     * @param $value object|array
     * @return Address
     */
    public static function createResourceFromApiResult($value): Address
    {
        if (is_array($value)) {
            $value = (object) $value;
        }

        $address = new self();
        $address->fullName = $value->fullName ?? '';
        $address->streetAndNumber = $value->streetAndNumber ?? '';
        $address->streetAdditional = $value->streetAdditional ?? '';
        $address->postalCode = $value->postalCode ?? '';
        $address->city = $value->city ?? '';
        $address->region = $value->region ?? '';
        $address->countryCode = $value->countryCode ?? '';
        $address->country = $value->country ?? '';
        $address->companyName = $value->companyName ?? '';
        $address->vatNumber = $value->vatNumber ?? '';
        $address->email = $value->email ?? '';

        return $address;
    }
}
