<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Builders;

use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Contracts\BillableInterface;
use Vatly\Fluent\Exceptions\IncompleteInformationException;
use Vatly\Fluent\Tests\TestCase;

class CheckoutBuilderTest extends TestCase
{
    private BillableInterface $owner;
    private CreateCheckout $createCheckout;
    private CheckoutBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createTestBillable('vat_owner_123');
        $this->createCheckout = $this->createMockCreateCheckout();
        $this->builder = new CheckoutBuilder($this->owner, $this->createCheckout);
    }

    public function test_it_builds_payload_with_owner_vatly_id(): void
    {
        $payload = $this->builder->payload();

        $this->assertSame('vat_owner_123', $payload['customerId']);
    }

    public function test_it_includes_items_in_payload(): void
    {
        $this->builder->withItems([
            ['id' => 'item_1', 'quantity' => 2],
            ['id' => 'item_2', 'quantity' => 1],
        ]);

        $payload = $this->builder->payload();

        $this->assertCount(2, $payload['products']);
        $this->assertSame('item_1', $payload['products'][0]['id']);
        $this->assertSame('item_2', $payload['products'][1]['id']);
    }

    public function test_it_includes_redirect_urls_in_payload(): void
    {
        $this->builder
            ->withRedirectUrlSuccess('https://example.com/success')
            ->withRedirectUrlCanceled('https://example.com/canceled');

        $payload = $this->builder->payload();

        $this->assertSame('https://example.com/success', $payload['redirectUrlSuccess']);
        $this->assertSame('https://example.com/canceled', $payload['redirectUrlCanceled']);
    }

    public function test_it_includes_metadata_in_payload(): void
    {
        $this->builder->withMetadata(['order_id' => '12345']);

        $payload = $this->builder->payload();

        $this->assertSame(['order_id' => '12345'], $payload['metadata']);
    }

    public function test_it_includes_testmode_in_payload(): void
    {
        $this->builder->withTestmode(true);
        $payload = $this->builder->payload();

        $this->assertTrue($payload['testmode']);
    }

    public function test_it_filters_null_values_by_default(): void
    {
        $payload = $this->builder->payload();

        $this->assertArrayNotHasKey('metadata', $payload);
    }

    public function test_it_can_include_null_values_when_filtered_is_false(): void
    {
        $payload = $this->builder->payload(filtered: false);

        $this->assertArrayHasKey('metadata', $payload);
        $this->assertNull($payload['metadata']);
    }

    public function test_it_merges_overrides(): void
    {
        $payload = $this->builder->payload(['extra' => 'value']);

        $this->assertSame('value', $payload['extra']);
    }

    public function test_with_redirect_url_success_returns_builder_instance(): void
    {
        $result = $this->builder->withRedirectUrlSuccess('https://example.com/success');

        $this->assertSame($this->builder, $result);
    }

    public function test_with_redirect_url_canceled_returns_builder_instance(): void
    {
        $result = $this->builder->withRedirectUrlCanceled('https://example.com/canceled');

        $this->assertSame($this->builder, $result);
    }

    public function test_with_metadata_returns_builder_instance(): void
    {
        $result = $this->builder->withMetadata(['key' => 'value']);

        $this->assertSame($this->builder, $result);
    }

    public function test_with_items_returns_builder_instance(): void
    {
        $result = $this->builder->withItems([['id' => 'item_1', 'quantity' => 1]]);

        $this->assertSame($this->builder, $result);
    }

    public function test_with_testmode_returns_builder_instance(): void
    {
        $result = $this->builder->withTestmode(true);

        $this->assertSame($this->builder, $result);
    }

    public function test_in_testmode_sets_testmode_to_true(): void
    {
        $this->builder->inTestmode();
        $payload = $this->builder->payload();

        $this->assertTrue($payload['testmode']);
    }

    public function test_in_live_mode_sets_testmode_to_false(): void
    {
        $this->builder->inTestmode()->inLiveMode();
        $payload = $this->builder->payload();

        $this->assertFalse($payload['testmode']);
    }

    public function test_it_throws_exception_when_no_items_provided(): void
    {
        $this->expectException(IncompleteInformationException::class);
        $this->expectExceptionMessage('No checkout items provided');

        $this->builder->create(
            items: [],
            redirectUrlSuccess: 'https://example.com/success',
            redirectUrlCanceled: 'https://example.com/canceled',
        );
    }

    private function createTestBillable(string $vatlyId): BillableInterface
    {
        return new class($vatlyId) implements BillableInterface {
            public function __construct(private string $vatlyId) {}

            public function getVatlyId(): string
            {
                return $this->vatlyId;
            }

            public function setVatlyId(string $id): void
            {
                $this->vatlyId = $id;
            }

            public function hasVatlyId(): bool
            {
                return $this->vatlyId !== '';
            }

            public function getVatlyEmail(): ?string
            {
                return 'test@example.com';
            }

            public function getVatlyName(): ?string
            {
                return 'Test User';
            }

            public function getKey(): string|int
            {
                return 1;
            }

            public function save(): mixed
            {
                return true;
            }
        };
    }

    private function createMockCreateCheckout(): CreateCheckout
    {
        return new class extends CreateCheckout {
            public function __construct() {}

            public function execute(array $payload, array $filters = []): Checkout
            {
                $checkout = new Checkout();
                $checkout->id = 'chk_test_123';
                $checkout->status = 'created';
                $checkout->testmode = false;
                $checkout->merchantId = 'merchant_test';
                $checkout->redirectUrlSuccess = 'https://example.com/success';
                $checkout->redirectUrlCanceled = 'https://example.com/canceled';

                return $checkout;
            }
        };
    }
}
