<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Exceptions;

use Mockery;
use Vatly\API\Endpoints\CheckoutEndpoint;
use Vatly\API\Endpoints\CustomerEndpoint;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\CreateCustomer;
use Vatly\Fluent\Actions\GetCheckout;
use Vatly\Fluent\Exceptions\ApiCallFailedException;
use Vatly\Fluent\Exceptions\VatlyException;
use Vatly\Fluent\Tests\TestCase;

class ApiCallFailedExceptionTest extends TestCase
{
    public function test_it_is_a_vatly_exception(): void
    {
        $wrapper = ApiCallFailedException::from(new ApiException('boom', 500));

        $this->assertInstanceOf(VatlyException::class, $wrapper);
        $this->assertInstanceOf(\RuntimeException::class, $wrapper);
    }

    public function test_from_preserves_code_message_and_original(): void
    {
        $original = new ApiException('Error 404 executing API call', 404);

        $wrapper = ApiCallFailedException::from($original);

        $this->assertSame('Error 404 executing API call', $wrapper->getMessage());
        $this->assertSame(404, $wrapper->getCode());
        $this->assertSame($original, $wrapper->getPrevious());
        $this->assertSame($original, $wrapper->apiException());
    }

    public function test_actions_wrap_api_exceptions_under_the_marker(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CheckoutEndpoint::class);
        $apiClient->checkouts = $endpoint;

        $endpoint->shouldReceive('get')
            ->once()
            ->andThrow(new ApiException('Error 422 executing API call', 422));

        $action = new GetCheckout($apiClient);

        try {
            $action->execute('checkout_1');
            $this->fail('Expected an ApiCallFailedException.');
        } catch (VatlyException $e) {
            $this->assertInstanceOf(ApiCallFailedException::class, $e);
            $this->assertSame(422, $e->getCode());
        }
    }

    public function test_create_customer_wraps_non_duplicate_api_errors(): void
    {
        $apiClient = Mockery::mock(VatlyApiClient::class);
        $endpoint = Mockery::mock(CustomerEndpoint::class);
        $apiClient->customers = $endpoint;

        // A 5xx that is not a "customer already exists" duplicate.
        $endpoint->shouldReceive('create')
            ->once()
            ->andThrow(new ApiException('Error 500 executing API call', 500));

        $action = new CreateCustomer($apiClient);

        $this->expectException(ApiCallFailedException::class);
        $this->expectExceptionCode(500);

        $action->execute(['email' => 'jane@example.test']);
    }
}
