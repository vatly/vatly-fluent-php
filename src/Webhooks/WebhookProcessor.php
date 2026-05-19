<?php

declare(strict_types=1);

namespace Vatly\Fluent\Webhooks;

use DateTimeImmutable;
use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Contracts\WebhookCallRepositoryInterface;
use Vatly\Fluent\Contracts\WebhookReactionInterface;

class WebhookProcessor
{
    private SignatureVerifier $signatureVerifier;
    private WebhookEventFactory $eventFactory;
    private WebhookCallRepositoryInterface $repository;
    private EventDispatcherInterface $dispatcher;
    private string $webhookSecret;

    /** @var WebhookReactionInterface[] */
    private array $reactions;

    /**
     * @param  WebhookReactionInterface[]  $reactions
     */
    public function __construct(
        SignatureVerifier $signatureVerifier,
        WebhookEventFactory $eventFactory,
        WebhookCallRepositoryInterface $repository,
        EventDispatcherInterface $dispatcher,
        string $webhookSecret,
        array $reactions = []
    ) {
        $this->signatureVerifier = $signatureVerifier;
        $this->eventFactory = $eventFactory;
        $this->repository = $repository;
        $this->dispatcher = $dispatcher;
        $this->webhookSecret = $webhookSecret;
        $this->reactions = $reactions;
    }

    /**
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
