<?php

declare(strict_types=1);

namespace Vatly\Fluent\Testing;

use Vatly\Fluent\Contracts\SubscriptionInterface;
use Vatly\Fluent\Contracts\SubscriptionWriter;
use Vatly\Fluent\Data\StoreSubscriptionData;
use Vatly\Fluent\Data\UpdateSubscriptionData;
use Vatly\Fluent\SubscriptionHandle;

/**
 * Recording {@see SubscriptionHandle} handed out by {@see FakeVatly}.
 *
 * State accessors (`getPlanId`, `isActive`, …) delegate to the underlying
 * {@see SubscriptionInterface} you pass in, so a test can build a scriptable
 * subscription state with a plain stub. The mutating operations
 * (`swap`, `cancel`, `resume`, …) are overridden to record on the `FakeVatly`
 * and short-circuit the API.
 */
final class FakeSubscriptionHandle extends SubscriptionHandle
{
    public function __construct(
        private readonly SubscriptionInterface $fakeSubscription,
        private readonly FakeVatly $vatly,
    ) {
        parent::__construct(
            subscription: $fakeSubscription,
            subscriptions: self::nullWriter(),
            swapAction: $vatly->swapSubscriptionPlan(),
            cancelAction: $vatly->cancelSubscription(),
            resumeAction: $vatly->resumeSubscription(),
            getSubscriptionAction: $vatly->getSubscription(),
            updateBillingAction: $vatly->updateSubscriptionBilling(),
        );
    }

    public function swap(string $planId, array $options = []): SubscriptionHandle
    {
        $this->vatly->recordSwap($this->fakeSubscription->getPlanId(), $planId);

        return $this;
    }

    public function swapAndInvoice(string $planId, array $options = []): SubscriptionHandle
    {
        return $this->swap($planId, $options);
    }

    public function cancel(): void
    {
        $this->vatly->recordCancellation($this->fakeSubscription->getVatlyId());
    }

    public function resume(): SubscriptionHandle
    {
        return $this;
    }

    public function updateBilling(array $prefillData = []): string
    {
        return 'https://billing.vatly.test/update/' . $this->fakeSubscription->getVatlyId();
    }

    public function sync(): SubscriptionHandle
    {
        return $this;
    }

    private static function nullWriter(): SubscriptionWriter
    {
        return new class implements SubscriptionWriter {
            public function store(StoreSubscriptionData $data): ?SubscriptionInterface
            {
                return null;
            }

            public function update(SubscriptionInterface $subscription, UpdateSubscriptionData $data): SubscriptionInterface
            {
                return $subscription;
            }
        };
    }
}
