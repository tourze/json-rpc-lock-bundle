# JsonRPC Lock Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/json-rpc-lock-bundle/ci.yml?branch=main&style=flat-square)](https://github.com/tourze/json-rpc-lock-bundle/actions)
[![PHP Version Require](https://img.shields.io/packagist/php-v/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![License](https://img.shields.io/packagist/l/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://codecov.io/gh/tourze/json-rpc-lock-bundle)

A Symfony Bundle that provides automatic locking functionality for JsonRPC interfaces to handle concurrent request control and idempotency.

## Features

- Automatic concurrency control for JsonRPC requests
- User-based lock resource identification
- Request idempotency handling with caching
- Graceful exception handling and logging
- Support for custom lock resource strategies
- Fallback retry mechanism for lock failures

## Dependencies

This bundle requires:
- PHP 8.1 or higher
- Symfony 7.3 or higher
- tourze/json-rpc-core
- tourze/lock-service-bundle
- tourze/backtrace-helper
- Symfony Security Bundle

## Installation

Install via Composer:

```bash
composer require tourze/json-rpc-lock-bundle
```

## Configuration

Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    Tourze\JsonRPCLockBundle\JsonRPCLockBundle::class => ['all' => true],
];
```

No additional configuration is required. The bundle works out of the box with sensible defaults.

## Quick Start

Create a JsonRPC procedure by extending `LockableProcedure`:

```php
<?php

use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;

class MySecureProcedure extends LockableProcedure
{
    public function execute(): array
    {
        // Your business logic here
        $params = $this->getParams();
        $userId = $params->get('user_id');
        $amount = $params->get('amount');
        
        // This will be automatically locked based on user identity
        return ['result' => $this->processPayment($userId, $amount)];
    }
    
    private function processPayment(int $userId, float $amount): string
    {
        // Your payment processing logic
        return 'payment_processed';
    }
}
```

## Advanced Usage

### Custom Lock Resources

Override the `getLockResource` method to customize locking behavior:

```php
public function getLockResource(JsonRpcParams $params): ?array
{
    // Custom lock based on specific parameters
    $accountId = $params->get('account_id');
    return ['account_lock_' . $accountId];
}

// To skip locking entirely, return null
public function getLockResource(JsonRpcParams $params): ?array
{
    return null; // No locking applied
}
```

### Idempotency Support

Enable request caching for idempotent operations:

```php
protected function getIdempotentCacheKey(JsonRpcRequest $request): ?string
{
    $params = $request->getParams();
    return 'payment_' . $params->get('user_id') . '_' . $params->get('transaction_id');
}
```

Cached results expire after 60 seconds by default. If a cached result exists, it will be returned immediately without acquiring locks or executing the procedure.

### Fallback Retry

Enable automatic retry without locking for non-critical operations:

```php
public function fallbackRetry(): bool
{
    return true; // Enable fallback retry on lock failures
}
```

## How It Works

1. **Lock Resource Preparation**: Based on user identity or procedure name
    - Authenticated users: Uses `user.getUserIdentifier()`
    - Anonymous requests: Uses procedure class name
    - Custom: Override `getLockResource()` method

2. **Idempotency Check**: Returns cached result if available and cache key is set

3. **Lock Acquisition**: Acquires distributed lock using `LockService.blockingRun()`

4. **Execution**: Runs the actual procedure logic via `parent::__invoke()`

5. **Result Caching**: Stores result for 60 seconds (if idempotent cache key is provided)

6. **Exception Handling**: 
    - Lock conflicts: Returns cached result or throws "你手速太快了，请稍候" message
    - General exceptions: Logs error and optionally retries without lock if `fallbackRetry()` returns true

## Environment Variables

- `JSON_RPC_RESPONSE_EXCEPTION_MESSAGE`: Custom message for lock conflict exceptions (default: "你手速太快了，请稍候")

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/json-rpc-lock-bundle/tests
```

For static analysis:

```bash
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/json-rpc-lock-bundle
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and add tests
4. Ensure tests pass (`./vendor/bin/phpunit`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.
