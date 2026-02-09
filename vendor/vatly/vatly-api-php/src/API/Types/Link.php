<?php

namespace Vatly\API\Types;

class Link
{
    public string $href;
    public string $type;


    public function __construct(string $href, string $type)
    {
        $this->href = $href;
        $this->type = $type;
    }
}
