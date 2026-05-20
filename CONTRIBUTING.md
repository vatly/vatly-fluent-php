# Contributing

Thanks for considering a contribution to `vatly-fluent-php`. This guide is for changes to this package itself — bug fixes, new contracts, new reactions, doc improvements, etc.

## Local setup

```bash
git clone https://github.com/Vatly/vatly-fluent-php.git
cd vatly-fluent-php
composer install
composer test
composer analyse
```

Tests are PHPUnit + Mockery, fully isolated — no framework or HTTP needed. The base `TestCase` extends `PHPUnit\Framework\TestCase` and uses `MockeryPHPUnitIntegration` for cleanup.

## Design principles

These are the constraints to preserve when changing code under `src/`:

- **No framework-specific imports.** Nothing under `src/` should import framework code. The webhook pipeline, the orchestrator, the reactions, and the actions are all built against framework-agnostic contracts so [`vatly/vatly-laravel`](https://github.com/Vatly/vatly-laravel) can depend on this package without us depending on Laravel.
- **Stateless domain logic.** Reactions, builders, the processor — none of them hold persistent state. State lives in the repository implementations the consumer provides.
- **Raw API resources.** Actions return `Vatly\API\Resources\*` directly. We deliberately don't add a response-wrapper layer.
- **Immutable DTOs** for repository inputs (`StoreSubscriptionData`, `UpdateOrderData`, …) and for typed events.
- **Webhook events carry their own data.** Don't synthesise timestamps in reactions — pull them from the event, which pulled them from Vatly.

## PR process

1. Branch off `main`.
2. Add or update tests for the change.
3. Run `composer test` and `composer analyse` locally — both must be green.
4. Open the PR. CI runs the same checks across PHP 8.0 – 8.4.

## License

By contributing, you agree your contributions are released under the [MIT license](LICENSE).
