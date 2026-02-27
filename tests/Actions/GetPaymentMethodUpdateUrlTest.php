<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Actions;

use Mockery;
use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetPaymentMethodUpdateUrl;
use Vatly\Fluent\Tests\TestCase;

class GetPaymentMethodUpdateUrlTest extends TestCase
{
    private VatlyApiClient $mockApiClient;
    private SubscriptionEndpoint $mockSubscriptionEndpoint;
    private GetPaymentMethodUpdateUrl $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiClient = Mockery::mock(VatlyApiClient::class);
        $this->mockSubscriptionEndpoint = Mockery::mock(SubscriptionEndpoint::class);
        $this->mockApiClient->subscriptions = $this->mockSubscriptionEndpoint;

        $this->action = new GetPaymentMethodUpdateUrl($this->mockApiClient);
    }

    public function test_it_returns_the_payment_method_update_url(): void
    {
        $subscriptionId = 'subscription_abc123';
        $expectedUrl = 'https://checkout.vatly.com/update-payment/abc123';

        $this->mockSubscriptionEndpoint
            ->shouldReceive('requestLinkForBillingDetailsUpdate')
            ->once()
            ->with($subscriptionId, [])
            ->andReturn(new Link($expectedUrl, 'text/html'));

        $response = $this->action->execute($subscriptionId);

        $this->assertInstanceOf(Link::class, $response);
        $this->assertSame($expectedUrl, $response->href);
        $this->assertSame('text/html', $response->type);
    }

    public function test_it_passes_prefill_data_to_the_api(): void
    {
        $subscriptionId = 'subscription_xyz789';
        $prefillData = [
            'billingAddress' => [
                'streetAndNumber' => '123 Main St',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ];

        $this->mockSubscriptionEndpoint
            ->shouldReceive('requestLinkForBillingDetailsUpdate')
            ->once()
            ->with($subscriptionId, $prefillData)
            ->andReturn(new Link('https://checkout.vatly.com/update/xyz', 'text/html'));

        $response = $this->action->execute($subscriptionId, $prefillData);

        $this->assertInstanceOf(Link::class, $response);
    }
}
