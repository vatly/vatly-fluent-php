<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use DateTimeImmutable;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;

class WebhookProcessor
{
    public function __construct(
        private readonly SignatureVerifier $signatureVerifier,
        private readonly WebhookEventFactory $eventFactory,
        private readonly WebhookCallRepositoryInterface $repository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $webhookSecret,
    ) {
        //
    }

    /**
     * Handle an incoming webhook request.
     *
     * @throws \Vatly\Fluent\Exceptions\InvalidWebhookSignatureException
     */
    public function handle(string $payload, string $signature): void
    {
        $this->signatureVerifier->verify($signature, $payload, $this->webhookSecret);

        $webhook = $this->eventFactory->parsePayload(json_decode($payload, true));

        $this->repository->record(
            eventName: $webhook->eventName,
            resourceId: $webhook->resourceId,
            resourceName: $webhook->resourceName,
            payload: json_decode($payload, true),
            raisedAt: new DateTimeImmutable($webhook->raisedAt),
            testmode: $webhook->testmode,
            vatlyCustomerId: $webhook->getCustomerId(),
        );

        $event = $this->eventFactory->createFromWebhook($webhook);

        $this->dispatcher->dispatch($event);
    }
}
