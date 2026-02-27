<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use DateTimeImmutable;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;

class WebhookProcessor
{
    /**
     * @param  WebhookReactionInterface[]  $reactions
     */
    public function __construct(
        private readonly SignatureVerifier $signatureVerifier,
        private readonly WebhookEventFactory $eventFactory,
        private readonly WebhookCallRepositoryInterface $repository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $webhookSecret,
        private readonly array $reactions = [],
    ) {
        //
    }

    /**
     * Handle an incoming webhook request.
     *
     * Flow: verify → parse → store → react → dispatch
     *
     * Reactions handle core billing logic (storing orders, syncing subscriptions).
     * The dispatcher fires the event for framework-specific listeners (emails, etc).
     *
     * @throws \Vatly\Fluent\Exceptions\InvalidWebhookSignatureException
     */
    public function handle(string $payload, string $signature): void
    {
        $this->signatureVerifier->verify($signature, $payload, $this->webhookSecret);

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $webhook = $this->eventFactory->parsePayload($decoded);

        $this->repository->record(
            eventName: $webhook->eventName,
            resourceId: $webhook->resourceId,
            resourceName: $webhook->resourceName,
            payload: $decoded,
            raisedAt: new DateTimeImmutable($webhook->raisedAt),
            testmode: $webhook->testmode,
            vatlyCustomerId: $webhook->getCustomerId(),
        );

        $event = $this->eventFactory->createFromWebhook($webhook);

        foreach ($this->reactions as $reaction) {
            if ($reaction->supports($event)) {
                $reaction->handle($event);
            }
        }

        $this->dispatcher->dispatch($event);
    }
}
