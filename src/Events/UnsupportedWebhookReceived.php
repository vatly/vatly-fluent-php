<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

class UnsupportedWebhookReceived
{
    public string $eventName;
    public string $resourceId;
    public string $resourceName;
    /** @var array<string, mixed> */
    public array $object;
    public string $raisedAt;
    public bool $testmode;

    /**
     * @param array<string, mixed> $object
     */
    public function __construct(string $eventName, string $resourceId, string $resourceName, array $object, string $raisedAt, bool $testmode)
    {
        $this->eventName = $eventName;
        $this->resourceId = $resourceId;
        $this->resourceName = $resourceName;
        $this->object = $object;
        $this->raisedAt = $raisedAt;
        $this->testmode = $testmode;
    }

    public static function fromWebhook(WebhookReceived $webhook): self
    {
        return new self(
            $webhook->eventName,
            $webhook->resourceId,
            $webhook->resourceName,
            $webhook->object,
            $webhook->raisedAt,
            $webhook->testmode
        );
    }
}
