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
 * Pattern borrowed from `league/flysystem`'s `FilesystemException`.
 */
interface VatlyException extends Throwable
{
}
