<?php

declare(strict_types=1);

namespace Vatly\Fluent\Concerns;

use Vatly\API\Exceptions\ApiException;
use Vatly\Fluent\Exceptions\ApiCallFailedException;

/**
 * Wrap a delegated `vatly-api-php` call so its {@see ApiException} surfaces as a
 * {@see ApiCallFailedException} — catchable through the
 * {@see \Vatly\Fluent\Exceptions\VatlyException} marker — instead of leaking the
 * raw api-php exception past the fluent boundary.
 *
 * Used by the action base class, the product services, and the test helpers:
 * every fluent surface that delegates to api-php routes the call through
 * {@see self::guardApiCall()}.
 */
trait GuardsApiCalls
{
    /**
     * Run an api-php-delegating call, converting any {@see ApiException} into an
     * {@see ApiCallFailedException} (code + message preserved).
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    protected function guardApiCall(callable $call)
    {
        try {
            return $call();
        } catch (ApiException $e) {
            throw ApiCallFailedException::from($e);
        }
    }
}
