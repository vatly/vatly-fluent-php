<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Customer;

class CreateCustomer extends BaseAction
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $filters
     * @param bool $returnExistingOnDuplicate Returns existing customer when email already exists (default: true)
     */
    public function execute(array $payload, array $filters = [], bool $returnExistingOnDuplicate = true): Customer
    {
        try {
            $customer = $this->vatlyApiClient->customers->create(
                payload: $payload,
                filters: $filters,
            );

            assert($customer instanceof Customer);

            return $customer;
        } catch (ApiException $e) {
            if ($returnExistingOnDuplicate && $existingCustomerId = $this->extractExistingCustomerId($e)) {
                $customer = $this->vatlyApiClient->customers->get($existingCustomerId);
                assert($customer instanceof Customer);

                return $customer;
            }

            throw $e;
        }
    }

    /**
     * Extract existing customer ID from a "customer already exists" API error.
     */
    private function extractExistingCustomerId(ApiException $e): ?string
    {
        $message = $e->getMessage();

        // Try to decode as JSON (API returns JSON error response)
        $decoded = json_decode($message, true);
        if (is_array($decoded) && isset($decoded['details']['existingCustomerId'])) {
            return $decoded['details']['existingCustomerId'];
        }

        // Fallback: extract from message pattern "Customer cust_xxxxx already exists"
        if (preg_match('/Customer\s+(cust_[a-zA-Z0-9]+)\s+already exists/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
