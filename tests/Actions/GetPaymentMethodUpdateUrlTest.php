<?php

declare(strict_types=1);

use Vatly\Actions\GetPaymentMethodUpdateUrl;
use Vatly\Actions\Responses\GetPaymentMethodUpdateUrlResponse;
use Vatly\API\Endpoints\SubscriptionEndpoint;
use Vatly\API\Types\Link;
use Vatly\API\VatlyApiClient;

beforeEach(function () {
    $this->mockApiClient = Mockery::mock(VatlyApiClient::class);
    $this->mockSubscriptionEndpoint = Mockery::mock(SubscriptionEndpoint::class);
    $this->mockApiClient->subscriptions = $this->mockSubscriptionEndpoint;
    
    $this->action = new GetPaymentMethodUpdateUrl($this->mockApiClient);
});

afterEach(function () {
    Mockery::close();
});

describe('execute', function () {
    test('it returns the payment method update URL', function () {
        $subscriptionId = 'subscription_abc123';
        $expectedUrl = 'https://checkout.vatly.com/update-payment/abc123';
        
        $this->mockSubscriptionEndpoint
            ->shouldReceive('requestLinkForBillingDetailsUpdate')
            ->once()
            ->with($subscriptionId, [])
            ->andReturn(new Link($expectedUrl, 'text/html'));
        
        $response = $this->action->execute($subscriptionId);
        
        expect($response)->toBeInstanceOf(GetPaymentMethodUpdateUrlResponse::class)
            ->and($response->url)->toBe($expectedUrl)
            ->and($response->type)->toBe('text/html');
    });
    
    test('it passes prefill data to the API', function () {
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
        
        expect($response)->toBeInstanceOf(GetPaymentMethodUpdateUrlResponse::class);
    });
});
