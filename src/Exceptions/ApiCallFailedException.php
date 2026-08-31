<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use RuntimeException;
use Vatly\API\Exceptions\ApiException;

/**
 * Wraps a `vatly-api-php` {@see ApiException} so that every failure surfaced by
 * the fluent SDK — including API transport / HTTP errors (404 unknown resource,
 * 422 validation, 5xx outage, connection failure) — is catchable through the
 * {@see VatlyException} marker.
 *
 * The original {@see ApiException} is preserved as the previous exception, and
 * its code and message are copied onto the wrapper, so existing checks such as
 * `catch (VatlyException $e) { if ($e->getCode() === 404) … }` keep working.
 * Reach the untouched original with {@see self::apiException()}.
 */
final class ApiCallFailedException extends RuntimeException implements VatlyException
{
    /**
     * Build the wrapper from the api-php exception, carrying its code + message
     * over and keeping the original as `$previous`.
     */
    public static function from(ApiException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous);
    }

    /**
     * The original api-php exception this wraps.
     */
    public function apiException(): ApiException
    {
        $previous = $this->getPrevious();

        assert($previous instanceof ApiException);

        return $previous;
    }
}
