<?php

namespace Vatly\API\Resources\Links;

use Vatly\API\Types\Link;

class OrderLinks extends BaseLinksResource
{
    public Link $customer;
    public ?Link $invoice;

    public ?Link $chargebacks;
}
