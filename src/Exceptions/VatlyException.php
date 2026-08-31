<?php

declare(strict_types=1);

namespace Vatly\Fluent\Exceptions;

use Throwable;

/**
 * Marker interface for all exceptions thrown by `vatly/vatly-fluent-php`.
 *
 * `catch (VatlyException $e)` catches every fluent-thrown exception
 * regardless of subclass. Catch a concrete class (e.g.
 * {@see InvalidOrderException}) to target one failure mode. Each
 * concrete is a `final class` extending one of PHP's SPL exception
 * classes (typically `\RuntimeException` or `\InvalidArgumentException`)
 * and implementing this marker.
 *
 * This includes **API transport / HTTP errors**: every fluent surface that
 * delegates to `vatly-api-php` wraps its `Vatly\API\Exceptions\ApiException`
 * (404 unknown resource, 422 validation, 5xx outage, connection failure) in an
 * {@see ApiCallFailedException}, which implements this marker and preserves the
 * original code + message. So a single `catch (VatlyException $e)` around a
 * checkout, subscribe, swap, cancel, customer update, or catalogue call handles
 * both fluent-level and API-level failures; `$e->getCode() === 404` still works,
 * and the raw exception is reachable via {@see ApiCallFailedException::apiException()}.
 *
 * Pattern borrowed from `league/flysystem`'s `FilesystemException`.
 */
interface VatlyException extends Throwable
{
}
