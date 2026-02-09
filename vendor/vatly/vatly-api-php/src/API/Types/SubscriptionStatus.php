<?php

namespace Vatly\API\Types;

class SubscriptionStatus
{
    public const ACTIVE = "active";

    public const CREATED = "created";

    public const CANCELED = "canceled";

    public const ON_GRACE_PERIOD = "on_grace_period";

    public const TRIAL = "trial";
}
