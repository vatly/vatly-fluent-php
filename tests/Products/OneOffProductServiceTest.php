<?php

declare(strict_types=1);

namespace Vatly\Fluent\Tests\Products;

use Mockery;
use Vatly\API\Endpoints\OneOffProductEndpoint;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\OneOffProduct;
use Vatly\API\Resources\OneOffProductCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Products\OneOffProductService;
use Vatly\Fluent\Exceptions\ApiCallFailedException;
use Vatly\Fluent\Tests\TestCase;

class OneOffProductServiceTest extends TestCase
{
    private VatlyApiClient $apiClient;
    private OneOffProductEndpoint $endpoint;
    private OneOffProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiClient = Mockery::mock(VatlyApiClient::class);
        $this->endpoint = Mockery::mock(OneOffProductEndpoint::class);
        $this->apiClient->oneOffProducts = $this->endpoint;
        $this->service = new OneOffProductService($this->apiClient);
    }

    public function test_create_forwards_to_the_endpoint(): void
    {
        $product = $this->makeProduct('one_off_product_1');

        $this->endpoint->shouldReceive('create')
            ->once()
            ->with(['name' => 'Ebook', 'basePrice' => ['value' => '9.00', 'currency' => 'EUR']], [])
            ->andReturn($product);

        $result = $this->service->create([
            'name' => 'Ebook',
            'basePrice' => ['value' => '9.00', 'currency' => 'EUR'],
        ]);

        $this->assertSame($product, $result);
    }

    public function test_find_forwards_to_the_endpoint(): void
    {
        $product = $this->makeProduct('one_off_product_1');

        $this->endpoint->shouldReceive('get')
            ->once()
            ->with('one_off_product_1', [])
            ->andReturn($product);

        $this->assertSame($product, $this->service->find('one_off_product_1'));
    }

    public function test_update_forwards_to_the_endpoint(): void
    {
        $product = $this->makeProduct('one_off_product_1');
        $product->updateStatus = 'pending';

        $this->endpoint->shouldReceive('update')
            ->once()
            ->with('one_off_product_1', ['name' => 'New name'], [])
            ->andReturn($product);

        $result = $this->service->update('one_off_product_1', ['name' => 'New name']);

        $this->assertSame($product, $result);
        $this->assertSame('pending', $result->updateStatus);
    }

    public function test_update_can_return_null(): void
    {
        $this->endpoint->shouldReceive('update')->once()->andReturnNull();

        $this->assertNull($this->service->update('one_off_product_1', ['name' => 'x']));
    }

    public function test_archive_forwards_to_the_endpoint(): void
    {
        $this->endpoint->shouldReceive('archive')
            ->once()
            ->with('one_off_product_1', []);

        $this->service->archive('one_off_product_1');
    }

    public function test_unarchive_forwards_to_the_endpoint(): void
    {
        $product = $this->makeProduct('one_off_product_1');

        $this->endpoint->shouldReceive('unarchive')
            ->once()
            ->with('one_off_product_1', [])
            ->andReturn($product);

        $this->assertSame($product, $this->service->unarchive('one_off_product_1'));
    }

    public function test_list_forwards_pagination_and_filters(): void
    {
        $collection = Mockery::mock(OneOffProductCollection::class);

        $this->endpoint->shouldReceive('page')
            ->once()
            ->with('after_1', null, 20, ['includeArchived' => true])
            ->andReturn($collection);

        $result = $this->service->list('after_1', null, 20, ['includeArchived' => true]);

        $this->assertSame($collection, $result);
    }

    public function test_it_wraps_api_exceptions_under_the_vatly_marker(): void
    {
        $this->endpoint->shouldReceive('create')
            ->once()
            ->andThrow(new ApiException('Error 422 executing API call', 422));

        $this->expectException(ApiCallFailedException::class);
        $this->expectExceptionCode(422);

        $this->service->create(['name' => 'Ebook']);
    }

    public function test_archive_wraps_api_exceptions(): void
    {
        $this->endpoint->shouldReceive('archive')
            ->once()
            ->andThrow(new ApiException('Error 404 executing API call', 404));

        $this->expectException(ApiCallFailedException::class);

        $this->service->archive('one_off_product_1');
    }

    private function makeProduct(string $id): OneOffProduct
    {
        $product = new OneOffProduct($this->apiClient);
        $product->id = $id;

        return $product;
    }
}
