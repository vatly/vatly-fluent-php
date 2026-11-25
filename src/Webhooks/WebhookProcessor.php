<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use DateTimeImmutable;
use InvalidArgumentException;
use Vatly\API\Exceptions\InvalidSignatureException;
use Vatly\API\Webhooks\Webhook;
use Vatly\API\Webhooks\WebhookEventFactory;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Exceptions\WebhookModeMismatchException;

/**
 * @immutable
 */
class WebhookProcessor
{
    /**
     * @param  WebhookReactionInterface[]  $reactions
     * @param  bool|null  $testmode  Whether the configured API key is a test key
     *                               (starts with `test_`). A webhook for the
     *                               other mode is refused. Null skips the check
     *                               and is kept only for existing callers;
     *                               {@see WebhookProcessorFactory} always passes it.
     */
    public function __construct(
        private WebhookEventFactory $eventFactory,
        private WebhookCallRepositoryInterface $repository,
        private EventDispatcherInterface $dispatcher,
        private string $webhookSecret,
        private array $reactions = [],
        private ?bool $testmode = null,
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
     * @throws InvalidWebhookSignatureException When no webhook secret is configured
     *                                          (see {@see InvalidWebhookSignatureException::isSecretNotConfigured()}),
     *                                          the signature is missing, the
     *                                          timestamp is outside the replay
     *                                          window, the HMAC does not match,
     *                                          or the payload structure is invalid.
     * @throws WebhookModeMismatchException When the webhook is for the other
     *                                      mode than the configured API key.
     *                                      It extends InvalidWebhookSignatureException.
     */
    public function handle(string $payload, string $signature): void
    {
        if (trim($this->webhookSecret) === '') {
            throw InvalidWebhookSignatureException::secretNotConfigured();
        }

        if ($signature === '') {
            throw InvalidWebhookSignatureException::missingSignature();
        }

        try {
            $parsed = Webhook::parse($payload, $signature, $this->webhookSecret);
        } catch (InvalidSignatureException|InvalidArgumentException) {
            throw InvalidWebhookSignatureException::invalidSignature();
        }

        $webhook = $this->eventFactory->fromPayload($parsed);

        if ($this->testmode !== null && $webhook->testmode !== $this->testmode) {
            throw new WebhookModeMismatchException($webhook->testmode);
        }

        $this->repository->record(
            id: $webhook->id,
            resource: $webhook->resource,
            eventName: $webhook->eventName,
            entityType: $webhook->entityType,
            entityId: $webhook->entityId,
            testmode: $webhook->testmode,
            createdAt: new DateTimeImmutable($webhook->createdAt),
            // The log never keeps the customer's personal data.
            object: WebhookCallRedactor::redact($webhook->object),
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
