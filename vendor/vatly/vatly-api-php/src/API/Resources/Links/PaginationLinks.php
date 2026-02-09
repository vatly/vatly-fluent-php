<?php

namespace Vatly\API\Resources\Links;

use Vatly\API\Types\Link;

class PaginationLinks extends BaseLinksResource
{
    public ?Link $previous;
    public ?Link $next;
}
