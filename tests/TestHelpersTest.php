<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests;

use Mockery;
use Vatly\API\Endpoints\TestHelpersEndpoint;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Exceptions\ApiCallFailedException;
use Vatly\Fluent\TestHelpers;

class TestHelpersTest extends TestCase
{
    private VatlyApiClient $apiClient;
    private TestHelpersEndpoint $endpoint;
    private TestHelpers $helpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiClient = Mockery::mock(VatlyApiClient::class);
        $this->endpoint = Mockery::mock(TestHelpersEndpoint::class);
        $this->apiClient->testHelpers = $this->endpoint;
        $this->helpers = new TestHelpers($this->apiClient);
    }

    public function test_advance_renewal_sends_an_empty_body(): void
    {
        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->with('subscription_1', [])
            ->andReturn(null);

        $this->assertNull($this->helpers->advanceRenewal('subscription_1'));
    }

    public function test_force_renewal_paid_sends_paid_status(): void
    {
        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->with('subscription_1', ['paymentStatus' => 'paid'])
            ->andReturn(null);

        $this->helpers->forceRenewalPaid('subscription_1');
    }

    public function test_force_renewal_failed_sends_failed_status_without_reason(): void
    {
        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->with('subscription_1', ['paymentStatus' => 'failed'])
            ->andReturn(null);

        $this->helpers->forceRenewalFailed('subscription_1');
    }

    public function test_force_renewal_failed_forwards_the_failure_reason(): void
    {
        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->with('subscription_1', ['paymentStatus' => 'failed', 'failureReason' => 'card_expired'])
            ->andReturn(null);

        $this->helpers->forceRenewalFailed('subscription_1', 'card_expired');
    }

    public function test_fast_forward_renewal_passes_an_arbitrary_body_through(): void
    {
        $response = (object) ['ok' => true];

        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->with('subscription_1', ['paymentStatus' => 'failed', 'failureReason' => 'insufficient_funds'])
            ->andReturn($response);

        $result = $this->helpers->fastForwardRenewal('subscription_1', [
            'paymentStatus' => 'failed',
            'failureReason' => 'insufficient_funds',
        ]);

        $this->assertSame($response, $result);
    }

    public function test_it_wraps_api_exceptions_under_the_vatly_marker(): void
    {
        $this->endpoint->shouldReceive('fastForwardSubscriptionRenewal')
            ->once()
            ->andThrow(new ApiException('Error 409 executing API call', 409));

        $this->expectException(ApiCallFailedException::class);
        $this->expectExceptionCode(409);

        $this->helpers->advanceRenewal('subscription_1');
    }
}
