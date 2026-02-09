<?php

namespace Vatly\API\Resources\Links;

use Vatly\API\Types\Link;

class CheckoutLinks extends BaseLinksResource
{
    public Link $checkoutUrl;
    public ?Link $order = null;
}
