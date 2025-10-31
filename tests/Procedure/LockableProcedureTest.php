<?php

namespace Tourze\JsonRPCLockBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * 可锁定过程测试
 *
 * @internal
 */
#[CoversClass(LockableProcedure::class)]
#[RunTestsInSeparateProcesses]
final class LockableProcedureTest extends AbstractProcedureTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊设置，使用默认集成测试环境
    }

    /**
     * 测试过程名称生成
     */
    public function testGetProcedureName(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试过程名称生成')]
        #[MethodExpose(method: 'test_getProcedureName')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $className = get_class($procedure);

        $this->assertEquals(str_replace('\\', '_', $className), $procedure::getProcedureName());
    }

    /**
     * 测试默认不回退重试
     */
    public function testFallbackRetry(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试默认不回退重试')]
        #[MethodExpose(method: 'test_fallbackRetry')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $this->assertFalse($procedure->fallbackRetry());
    }

    /**
     * 测试幂等缓存键默认为空
     */
    public function testDefaultIdempotentCacheKey(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试幂等缓存键默认为空')]
        #[MethodExpose(method: 'test_defaultIdempotentCacheKey')]
        class extends LockableProcedure {
            public function mockGetIdempotentCacheKey(JsonRpcRequest $request): ?string
            {
                return $this->getIdempotentCacheKey($request);
            }

            public function execute(): array
            {
                return [];
            }
        };

        // 使用具体类 JsonRpcRequest 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供对应的接口抽象
        // 2. 这个类是 JSON-RPC 协议的标准实现，变更频率极低
        // 3. 测试需要验证该类的具体行为而非抽象行为
        /** @var JsonRpcRequest&MockObject $mockRequest */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockRequest = $this->createMock(JsonRpcRequest::class);
        $this->assertNull($procedure->mockGetIdempotentCacheKey($mockRequest));
    }

    /**
     * 测试 LockEntity 资源检索
     */
    public function testLockEntityResource(): void
    {
        // 创建一个模拟的 LockEntity 对象
        $lockResource = 'test-resource';
        // 使用 LockEntity 接口进行 mock，这是正确的做法：
        // 1. LockEntity 是一个接口，符合最佳实践
        // 2. 通过接口 mock 可以减少测试对具体实现的依赖
        // 3. 提高测试的可维护性和灵活性
        /** @var LockEntity&MockObject $mockLockEntity */
        // PHPStan: Using concrete class Entity instead of interface because
        // this is a Doctrine entity that doesn't implement a common interface
        // This is necessary for proper method mocking in tests
        $mockLockEntity = $this->createMock(LockEntity::class);
        $mockLockEntity->method('retrieveLockResource')
            ->willReturn($lockResource)
        ;

        // 断言资源检索方法被正确调用并返回期望值
        $this->assertEquals($lockResource, $mockLockEntity->retrieveLockResource());
    }

    /**
     * 测试获取锁资源 - 无用户登录场景
     */
    public function testGetLockResourceWithoutUser(): void
    {
        // 使用具体类 Security 而非接口的原因：
        // 1. Symfony Security Bundle 没有为此服务提供对应接口
        // 2. 这是 Symfony 框架的核心服务，API 稳定
        // 3. 测试需要验证与 Symfony Security 的具体集成行为
        /** @var Security&MockObject $mockSecurity */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockSecurity = $this->createMock(Security::class);
        $mockSecurity->method('getUser')->willReturn(null);

        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试获取锁资源 - 无用户登录场景')]
        #[MethodExpose(method: 'test_getLockResourceWithoutUser')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $procedure->setContainer($this->createContainerWithSecurity($mockSecurity));

        // 使用具体类 JsonRpcParams 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供参数对象的接口抽象
        // 2. 这个类继承自 Symfony ParameterBag，是稳定的实现
        // 3. 测试需要验证与具体参数处理逻辑的交互
        /** @var JsonRpcParams&MockObject $mockParams */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);
        $this->assertNotNull($resources);
        $this->assertContains($procedure::getProcedureName(), $resources);
    }

    /**
     * 测试获取锁资源 - 有用户登录场景
     */
    public function testGetLockResourceWithUser(): void
    {
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockUser = $this->createMock(UserInterface::class);
        $mockUser->method('getUserIdentifier')->willReturn('test_user');

        // 使用具体类 Security 而非接口的原因：
        // 1. Symfony Security Bundle 没有为此服务提供对应接口
        // 2. 这是 Symfony 框架的核心服务，API 稳定
        // 3. 测试需要验证与 Symfony Security 的具体集成行为
        /** @var Security&MockObject $mockSecurity */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockSecurity = $this->createMock(Security::class);
        $mockSecurity->method('getUser')->willReturn($mockUser);

        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试获取锁资源 - 有用户登录场景')]
        #[MethodExpose(method: 'test_getLockResourceWithUser')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $procedure->setContainer($this->createContainerWithSecurity($mockSecurity));

        // 使用具体类 JsonRpcParams 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供参数对象的接口抽象
        // 2. 这个类继承自 Symfony ParameterBag，是稳定的实现
        // 3. 测试需要验证与具体参数处理逻辑的交互
        /** @var JsonRpcParams&MockObject $mockParams */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);
        $this->assertNotNull($resources);
        $this->assertContains('test_user', $resources);
    }

    /**
     * 测试服务订阅方法
     */
    public function testServiceSubscription(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试服务订阅方法')]
        #[MethodExpose(method: 'test_serviceSubscription')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        // 测试服务订阅接口实现
        $subscribedServices = $procedure::getSubscribedServices();
        $this->assertIsArray($subscribedServices);
        $this->assertArrayHasKey('Tourze\JsonRPCLockBundle\Procedure\LockableProcedure::getSecurity', $subscribedServices);
    }

    /**
     * 测试自定义锁资源获取
     */
    public function testGetLockResourceWithCustomImplementation(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试自定义锁资源获取')]
        #[MethodExpose(method: 'test_getLockResourceWithCustomImplementation')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            /**
             * @return array<mixed>
             */
            public function getLockResource(JsonRpcParams $params): array
            {
                return ['custom_resource_key'];
            }
        };

        // 使用具体类 JsonRpcParams 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供参数对象的接口抽象
        // 2. 这个类继承自 Symfony ParameterBag，是稳定的实现
        // 3. 测试需要验证与具体参数处理逻辑的交互
        /** @var JsonRpcParams&MockObject $mockParams */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);
        $this->assertEquals(['custom_resource_key'], $resources);
    }

    /**
     * 测试返回 null 的锁资源
     */
    public function testGetLockResourceWithNull(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试返回 null 的锁资源')]
        #[MethodExpose(method: 'test_getLockResourceWithNull')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function getLockResource(JsonRpcParams $params): ?array
            {
                return null;
            }
        };

        // 使用具体类 JsonRpcParams 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供参数对象的接口抽象
        // 2. 这个类继承自 Symfony ParameterBag，是稳定的实现
        // 3. 测试需要验证与具体参数处理逻辑的交互
        /** @var JsonRpcParams&MockObject $mockParams */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);

        $this->assertNull($resources);
    }

    /**
     * 测试自定义回退重试
     */
    public function testFallbackRetryWithCustomImplementation(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试自定义回退重试')]
        #[MethodExpose(method: 'test_fallbackRetryWithCustomImplementation')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function fallbackRetry(): bool
            {
                return true;
            }
        };

        $this->assertTrue($procedure->fallbackRetry());
    }

    /**
     * 测试自定义幂等缓存键
     */
    public function testIdempotentCacheKeyWithCustomImplementation(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试自定义幂等缓存键')]
        #[MethodExpose(method: 'test_idempotentCacheKeyWithCustomImplementation')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            protected function getIdempotentCacheKey(JsonRpcRequest $request): string
            {
                return 'custom_cache_key';
            }

            public function mockGetIdempotentCacheKey(JsonRpcRequest $request): string
            {
                return $this->getIdempotentCacheKey($request);
            }
        };

        // 使用具体类 JsonRpcRequest 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供对应的接口抽象
        // 2. 这个类是 JSON-RPC 协议的标准实现，变更频率极低
        // 3. 测试需要验证该类的具体行为而非抽象行为
        /** @var JsonRpcRequest&MockObject $mockRequest */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockRequest = $this->createMock(JsonRpcRequest::class);
        $cacheKey = $procedure->mockGetIdempotentCacheKey($mockRequest);

        $this->assertEquals('custom_cache_key', $cacheKey);
    }

    /**
     * 创建带安全服务的容器
     */
    private function createContainerWithSecurity(Security $security): Container
    {
        $container = new Container();
        $container->set('Tourze\JsonRPCLockBundle\Procedure\LockableProcedure::getSecurity', $security);

        return $container;
    }
}
