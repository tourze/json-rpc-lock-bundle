# JsonRPC Lock Bundle

JsonRPC锁定处理 Symfony Bundle，用于为 JsonRPC 接口提供自动锁定功能。

## 功能特点

- 为 JsonRPC 请求提供并发锁定控制
- 支持基于用户身份的锁定资源识别
- 提供请求幂等性处理
- 异常处理和日志记录

## 安装

通过 Composer 安装:

```bash
composer require tourze/json-rpc-lock-bundle
```

## 使用方法

1. 在你的 Symfony 应用中注册 Bundle:

```php
// config/bundles.php
return [
    // ...
    Tourze\JsonRPCLockBundle\JsonRPCLockBundle::class => ['all' => true],
];
```

2. 创建继承自 LockableProcedure 的 JsonRPC 过程类:

```php
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;

class MyProcedure extends LockableProcedure
{
    protected function execute(JsonRpcParams $params): mixed
    {
        // 你的业务逻辑
        return $result;
    }
    
    // 可选：自定义锁资源标识
    protected function getLockResource(JsonRpcParams $params): ?array
    {
        return ['custom_resource_' . $params->get('id')];
    }
    
    // 可选：启用幂等性缓存
    protected function getIdempotentCacheKey(JsonRpcRequest $request): ?string
    {
        return 'my_procedure_' . $request->getParams()->get('id');
    }
}
```

## 测试

运行测试:

```bash
./vendor/bin/phpunit packages/json-rpc-lock-bundle/tests
```
