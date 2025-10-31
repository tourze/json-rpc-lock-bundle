# JSON-RPC 锁定处理 Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/json-rpc-lock-bundle/ci.yml?branch=main&style=flat-square)](https://github.com/tourze/json-rpc-lock-bundle/actions)
[![PHP 版本要求](https://img.shields.io/packagist/php-v/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![许可证](https://img.shields.io/packagist/l/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![最新版本](https://img.shields.io/packagist/v/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-lock-bundle)
[![代码覆盖率](https://img.shields.io/codecov/c/github/tourze/json-rpc-lock-bundle.svg?style=flat-square)](https://codecov.io/gh/tourze/json-rpc-lock-bundle)

一个 Symfony Bundle，为 JsonRPC 接口提供自动锁定功能，处理并发请求控制和幂等性。

## 功能特点

- 为 JsonRPC 请求提供自动并发控制
- 基于用户身份的锁资源识别
- 支持请求幂等性处理和缓存
- 优雅的异常处理和日志记录
- 支持自定义锁资源策略
- 锁失败时的后备重试机制

## 依赖要求

此 Bundle 要求:
- PHP 8.1 或更高版本
- Symfony 7.3 或更高版本
- tourze/json-rpc-core
- tourze/lock-service-bundle
- tourze/backtrace-helper
- Symfony Security Bundle

## 安装

通过 Composer 安装:

```bash
composer require tourze/json-rpc-lock-bundle
```

## 配置

在你的 Symfony 应用中注册 Bundle:

```php
// config/bundles.php
return [
    // ...
    Tourze\JsonRPCLockBundle\JsonRPCLockBundle::class => ['all' => true],
];
```

无需额外配置。Bundle 使用合理的默认设置即可开箱即用。

## 快速开始

通过继承 `LockableProcedure` 创建 JsonRPC 过程:

```php
<?php

use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;

class MySecureProcedure extends LockableProcedure
{
    public function execute(): array
    {
        // 你的业务逻辑
        $params = $this->getParams();
        $userId = $params->get('user_id');
        $amount = $params->get('amount');
        
        // 这将根据用户身份自动加锁
        return ['result' => $this->processPayment($userId, $amount)];
    }
    
    private function processPayment(int $userId, float $amount): string
    {
        // 你的支付处理逻辑
        return 'payment_processed';
    }
}
```

## 高级用法

### 自定义锁资源

重写 `getLockResource` 方法来自定义锁定行为:

```php
public function getLockResource(JsonRpcParams $params): ?array
{
    // 基于特定参数的自定义锁
    $accountId = $params->get('account_id');
    return ['account_lock_' . $accountId];
}

// 要完全跳过锁定，返回 null
public function getLockResource(JsonRpcParams $params): ?array
{
    return null; // 不应用锁定
}
```

### 幂等性支持

为幂等操作启用请求缓存:

```php
protected function getIdempotentCacheKey(JsonRpcRequest $request): ?string
{
    $params = $request->getParams();
    return 'payment_' . $params->get('user_id') . '_' . $params->get('transaction_id');
}
```

缓存结果默认在 60 秒后过期。如果存在缓存结果，将立即返回而不获取锁或执行过程。

### 后备重试

为非关键操作启用无锁自动重试:

```php
public function fallbackRetry(): bool
{
    return true; // 锁失败时启用后备重试
}
```

## 工作原理

1. **锁资源准备**: 基于用户身份或过程名称
    - 已认证用户: 使用 `user.getUserIdentifier()`
    - 匿名请求: 使用过程类名
    - 自定义: 重写 `getLockResource()` 方法

2. **幂等性检查**: 如果可用且设置了缓存键则返回缓存结果

3. **锁获取**: 使用 `LockService.blockingRun()` 获取分布式锁

4. **执行**: 通过 `parent::__invoke()` 运行实际的过程逻辑

5. **结果缓存**: 存储结果 60 秒（如果提供了幂等缓存键）

6. **异常处理**: 
    - 锁冲突: 返回缓存结果或抛出"你手速太快了，请稍候"消息
    - 一般异常: 记录错误，如果 `fallbackRetry()` 返回 true 则可选择无锁重试

## 环境变量

- `JSON_RPC_RESPONSE_EXCEPTION_MESSAGE`: 锁冲突异常的自定义消息（默认: "你手速太快了，请稍候"）

## 测试

运行测试套件:

```bash
./vendor/bin/phpunit packages/json-rpc-lock-bundle/tests
```

静态分析:

```bash
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/json-rpc-lock-bundle
```

## 贡献

1. Fork 此仓库
2. 创建你的功能分支 (`git checkout -b feature/amazing-feature`)
3. 进行更改并添加测试
4. 确保测试通过 (`./vendor/bin/phpunit`)
5. 提交你的更改 (`git commit -m 'Add amazing feature'`)
6. 推送到分支 (`git push origin feature/amazing-feature`)
7. 开启 Pull Request

## 许可证

此 Bundle 基于 MIT 许可证发布。详情请查看 [LICENSE](LICENSE) 文件。
