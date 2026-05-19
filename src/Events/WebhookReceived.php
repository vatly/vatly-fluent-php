<?php

declare(strict_types=1);

namespace Vatly\Fluent\Events;

/**
 * Event representing a raw webhook call received from Vatly.
 */
class WebhookReceived
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
    public function __construct(
        string $eventName,
        string $resourceId,
        string $resourceName,
        array $object,
        string $raisedAt,
        bool $testmode
    ) {
        $this->eventName = $eventName;
        $this->resourceId = $resourceId;
        $this->resourceName = $resourceName;
        $this->object = $object;
        $this->raisedAt = $raisedAt;
        $this->testmode = $testmode;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventName' => $this->eventName,
            'resourceId' => $this->resourceId,
            'resourceName' => $this->resourceName,
            'object' => $this->object,
            'raisedAt' => $this->raisedAt,
            'testmode' => $this->testmode,
        ];
    }

    public function getCustomerId(): ?string
    {
        return $this->object['data']['customerId'] ?? null;
    }
}
