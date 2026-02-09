<?php

namespace Vatly\API\Resources\Links;

use Vatly\API\Types\Link;

class ChargebackLinks extends BaseLinksResource
{
    public Link $customer;
    public Link $originalOrder;

    public ?Link $order;
}
