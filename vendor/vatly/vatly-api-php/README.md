# Vatly API client for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-api-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-api-php)
[![Tests](https://github.com/vatly/vatly-api-php/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/vatly/vatly-api-php/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-api-php.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-api-php)

Sell worldwide, today, with Vatly. Dedicated to EU based SAAS merchants and software companies, accept creditcard, PayPal, ApplePay, iDEAL and more.

## Installation

You can install the package via composer:

```bash
composer require vatly/vatly-api-php
```

## Usage

```php
$vatly = new Vatly\Api\VatlyApiClient;

$vatly->checkouts->create([...]);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Send in a Pull Request if you'd like to contribute to this package.

## Security Vulnerabilities

In case of a security vulnerability, please shoot us an email at security@vatly.com.

## Credits

- [Vatly.com](https://www.vatly.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
