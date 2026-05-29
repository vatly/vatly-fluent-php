<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use DateTimeInterface;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\ResumeSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionWriter;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\Exceptions\IncompleteInformationException;

/**
 * Framework-agnostic operations on a subscription.
 *
 * Wraps a SubscriptionInterface (the persistent state, owned by the driver)
 * with the actions that operate on it. Drivers expose this via
 * Vatly::subscription().
 */
class SubscriptionHandle
{
    public function __construct(
        private SubscriptionInterface $subscription,
        private SubscriptionWriter $subscriptions,
        private SwapSubscriptionPlan $swapAction,
        private CancelSubscription $cancelAction,
        private ResumeSubscription $resumeAction,
        private GetSubscription $getSubscriptionAction,
        private UpdateSubscriptionBilling $updateBillingAction,
    ) {
        //
    }

    /**
     * The underlying persistent subscription record.
     */
    public function model(): SubscriptionInterface
    {
        return $this->subscription;
    }

    // --- State accessors (delegate to SubscriptionInterface) ---

    public function getVatlyId(): string
    {
        return $this->subscription->getVatlyId();
    }

    public function getType(): string
    {
        return $this->subscription->getType();
    }

    public function getPlanId(): string
    {
        return $this->subscription->getPlanId();
    }

    public function getName(): string
    {
        return $this->subscription->getName();
    }

    public function getQuantity(): int
    {
        return $this->subscription->getQuantity();
    }

    public function getEndsAt(): ?DateTimeInterface
    {
        return $this->subscription->getEndsAt();
    }

    public function isCancelled(): bool
    {
        return $this->subscription->isCancelled();
    }

    public function cancelled(): bool
    {
        return $this->subscription->isCancelled();
    }

    public function isOnGracePeriod(): bool
    {
        return $this->subscription->isOnGracePeriod();
    }

    public function onGracePeriod(): bool
    {
        return $this->subscription->isOnGracePeriod();
    }

    public function isActive(): bool
    {
        return $this->subscription->isActive();
    }

    public function active(): bool
    {
        return $this->subscription->isActive();
    }

    public function isValid(): bool
    {
        return $this->subscription->isValid();
    }

    public function valid(): bool
    {
        return $this->subscription->isValid();
    }

    public function isRecurring(): bool
    {
        return $this->subscription->isRecurring();
    }

    public function recurring(): bool
    {
        return $this->subscription->isRecurring();
    }

    public function isEnded(): bool
    {
        return $this->subscription->isEnded();
    }

    public function ended(): bool
    {
        return $this->subscription->isEnded();
    }

    // --- Operations ---

    /**
     * Swap the subscription to a different plan.
     *
     * @param array<string, mixed> $options Extra parameters passed to the Vatly API
     *                                      (e.g. applyImmediately, invoiceImmediately).
     */
    public function swap(string $planId, array $options = []): self
    {
        $response = $this->swapAction->execute(
            $this->subscription->getVatlyId(),
            $planId,
            $options,
        );

        $updated = $this->subscriptions->update(
            $this->subscription,
            new UpdateSubscriptionData(
                planId: $response->subscriptionPlanId,
                quantity: $response->quantity,
            ),
        );

        $this->subscription = $updated;

        return $this;
    }

    /**
     * Swap to a new plan and invoice immediately.
     *
     * Applies the plan change right away and creates an invoice for prorated charges.
     *
     * @param array<string, mixed> $options
     */
    public function swapAndInvoice(string $planId, array $options = []): self
    {
        $options['applyImmediately'] = true;
        $options['invoiceImmediately'] = true;

        return $this->swap($planId, $options);
    }

    /**
     * Resume a subscription that is currently on its grace period.
     *
     * Reverses a pending cancellation while the subscription is still active.
     * Clears the local end date so the subscription is treated as active again.
     */
    public function resume(): self
    {
        $response = $this->resumeAction->execute($this->subscription->getVatlyId());

        $updated = $this->subscriptions->update(
            $this->subscription,
            new UpdateSubscriptionData(
                planId: $response->subscriptionPlanId,
                quantity: $response->quantity,
                clearEndsAt: true,
            ),
        );

        $this->subscription = $updated;

        return $this;
    }

    /**
     * Create a signed URL where the customer can update billing details
     * (billing address, VAT number, company name) via a hosted flow.
     *
     * Each call returns a fresh time-bounded link.
     *
     * Missing `redirectUrlSuccess` / `redirectUrlCanceled` keys are filled in
     * from the configured defaults
     * ({@see \Vatly\Fluent\Contracts\ConfigurationInterface::getDefaultRedirectUrlSuccess()}
     * and `getDefaultRedirectUrlCanceled()`); caller-supplied values always win.
     * If neither the caller nor the config provides a value, an
     * {@see IncompleteInformationException} is thrown.
     *
     * @param array<string, mixed> $prefillData May override `redirectUrlSuccess` /
     *                                          `redirectUrlCanceled`, and may include
     *                                          `billingAddress` as an optional prefill.
     *
     * @throws IncompleteInformationException When a required redirect URL resolves to an empty string.
     */
    public function updateBilling(array $prefillData = []): string
    {
        $link = $this->updateBillingAction->execute(
            $this->subscription->getVatlyId(),
            $prefillData,
        );

        return $link->href;
    }

    /**
     * Cancel the subscription at Vatly.
     *
     * The webhook reaction is responsible for updating the local end date
     * once Vatly confirms (immediate vs. grace period).
     */
    public function cancel(): void
    {
        $this->cancelAction->execute($this->subscription->getVatlyId());
    }

    /**
     * Refresh the local subscription record from Vatly.
     */
    public function sync(): self
    {
        $response = $this->getSubscriptionAction->execute($this->subscription->getVatlyId());

        $endsAt = $this->resolveEndsAt($response);

        $updated = $this->subscriptions->update(
            $this->subscription,
            new UpdateSubscriptionData(
                planId: $response->subscriptionPlanId,
                name: $response->name,
                quantity: $response->quantity,
                endsAt: $endsAt,
            ),
        );

        $this->subscription = $updated;

        return $this;
    }

    private function resolveEndsAt(\Vatly\API\Resources\Subscription $response): ?DateTimeInterface
    {
        if ($response->endedAt !== null) {
            return new \DateTimeImmutable($response->endedAt);
        }

        if ($response->canceledAt !== null && $this->subscription->getEndsAt() === null) {
            return new \DateTimeImmutable($response->canceledAt);
        }

        return $this->subscription->getEndsAt();
    }
}
