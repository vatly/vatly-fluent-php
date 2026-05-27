<?php

declare(strict_types=1);

namespace Vatly\Fluent;

use DateTimeInterface;
use Vatly\Fluent\Actions\CancelSubscription;
use Vatly\Fluent\Actions\UpdateSubscriptionBilling;
use Vatly\Fluent\Actions\GetSubscription;
use Vatly\Fluent\Actions\ResumeSubscription;
use Vatly\Fluent\Actions\SwapSubscriptionPlan;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionRepositoryInterface;
use Vatly\Fluent\Data\UpdateSubscriptionData;

/**
 * Framework-agnostic operations on a subscription.
 *
 * Wraps a SubscriptionInterface (the persistent state, owned by the driver)
 * with the actions that operate on it. Drivers expose this via
 * Billable::subscription().
 */
class SubscriptionHandle
{
    public function __construct(
        private SubscriptionInterface $subscription,
        private SubscriptionRepositoryInterface $subscriptions,
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

    public function getOwner(): BillableInterface
    {
        return $this->subscription->getOwner();
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
     * @param array<string, mixed> $prefillData Optional pre-fill data.
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

        if ($response->cancelledAt !== null && $this->subscription->getEndsAt() === null) {
            return new \DateTimeImmutable($response->cancelledAt);
        }

        return $this->subscription->getEndsAt();
    }
}
