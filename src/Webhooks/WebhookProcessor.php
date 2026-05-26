<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use DateTimeImmutable;
use InvalidArgumentException;
use Vatly\API\Exceptions\InvalidSignatureException;
use Vatly\API\Webhooks\Webhook;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;

/**
 * @immutable
 */
class WebhookProcessor
{
    /**
     * @param  WebhookReactionInterface[]  $reactions
     */
    public function __construct(
        private WebhookEventFactory $eventFactory,
        private WebhookCallRepositoryInterface $repository,
        private EventDispatcherInterface $dispatcher,
        private string $webhookSecret,
        private array $reactions = [],
    ) {
        //
    }

    /**
     * Handle an incoming webhook request.
     *
     * Flow: parse (verify + decode + validate) → store → react → dispatch
     *
     * Verification and structural validation are delegated to
     * {@see Webhook::parse()}; this method only deals with the
     * downstream bookkeeping. Reactions handle core billing logic
     * (storing orders, syncing subscriptions). The dispatcher fires the
     * event for framework-specific listeners (emails, etc).
     *
     * @throws InvalidWebhookSignatureException When the signature is missing, the
     *                                          timestamp is outside the replay
     *                                          window, the HMAC does not match,
     *                                          or the payload structure is invalid.
     */
    public function handle(string $payload, string $signature): void
    {
        if ($signature === '') {
            throw InvalidWebhookSignatureException::missingSignature();
        }

        try {
            $parsed = Webhook::parse($payload, $signature, $this->webhookSecret);
        } catch (InvalidSignatureException|InvalidArgumentException) {
            throw InvalidWebhookSignatureException::invalidSignature();
        }

        $webhook = $this->eventFactory->fromPayload($parsed);

        $this->repository->record(
            id: $webhook->id,
            resource: $webhook->resource,
            eventName: $webhook->eventName,
            entityType: $webhook->entityType,
            entityId: $webhook->entityId,
            testmode: $webhook->testmode,
            createdAt: new DateTimeImmutable($webhook->createdAt),
            object: $webhook->object,
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

    /**
     * @return WebhookReactionInterface[]
     */
    public function getReactions(): array
    {
        return $this->reactions;
    }
}
