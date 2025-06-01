<?php

namespace Tourze\JsonRPCLockBundle\Tests\Procedure;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * 可锁定过程测试
 */
class LockableProcedureTest extends TestCase
{
    /**
     * 测试过程名称生成
     */
    public function testGetProcedureName(): void
    {
        /** @var LockableProcedure&MockObject $procedure */
        $procedure = $this->getMockForAbstractClass(LockableProcedure::class);
        $className = get_class($procedure);

        $this->assertEquals(str_replace('\\', '_', $className), $procedure::getProcedureName());
    }

    /**
     * 测试默认不回退重试
     */
    public function testFallbackRetry(): void
    {
        /** @var LockableProcedure&MockObject $procedure */
        $procedure = $this->getMockForAbstractClass(LockableProcedure::class);
        $this->assertFalse($procedure->fallbackRetry());
    }

    /**
     * 测试幂等缓存键默认为空
     */
    public function testDefaultIdempotentCacheKey(): void
    {
        $procedure = new class extends LockableProcedure {
            public function mockGetIdempotentCacheKey(JsonRpcRequest $request): ?string
            {
                return $this->getIdempotentCacheKey($request);
            }

            public function execute(): array
            {
                return [];
            }
        };

        /** @var JsonRpcRequest&MockObject $mockRequest */
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
        /** @var LockEntity&MockObject $mockLockEntity */
        $mockLockEntity = $this->createMock(LockEntity::class);
        $mockLockEntity->method('retrieveLockResource')
            ->willReturn($lockResource);

        // 断言资源检索方法被正确调用并返回期望值
        $this->assertEquals($lockResource, $mockLockEntity->retrieveLockResource());
    }

    /**
     * 测试获取锁资源 - 无用户登录场景
     */
    public function testGetLockResource_WithoutUser(): void
    {
        /** @var Security&MockObject $mockSecurity */
        $mockSecurity = $this->createMock(Security::class);
        $mockSecurity->method('getUser')->willReturn(null);

        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $procedure->setContainer($this->createContainerWithSecurity($mockSecurity));

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);

        $this->assertIsArray($resources);
        $this->assertContains($procedure::getProcedureName(), $resources);
    }

    /**
     * 测试获取锁资源 - 有用户登录场景
     */
    public function testGetLockResource_WithUser(): void
    {
        $mockUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $mockUser->method('getUserIdentifier')->willReturn('test_user');

        /** @var Security&MockObject $mockSecurity */
        $mockSecurity = $this->createMock(Security::class);
        $mockSecurity->method('getUser')->willReturn($mockUser);

        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };
        $procedure->setContainer($this->createContainerWithSecurity($mockSecurity));

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);

        $this->assertIsArray($resources);
        $this->assertContains('test_user', $resources);
    }

    /**
     * 测试服务订阅方法
     */
    public function testServiceSubscription(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        // 测试服务订阅接口实现
        $this->assertInstanceOf(\Symfony\Contracts\Service\ServiceSubscriberInterface::class, $procedure);
    }

    /**
     * 测试自定义锁资源获取
     */
    public function testGetLockResource_WithCustomImplementation(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function getLockResource(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): ?array
            {
                return ['custom_resource_key'];
            }
        };

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);

        $this->assertIsArray($resources);
        $this->assertEquals(['custom_resource_key'], $resources);
    }

    /**
     * 测试返回 null 的锁资源
     */
    public function testGetLockResource_WithNull(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function getLockResource(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): ?array
            {
                return null;
            }
        };

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $resources = $procedure->getLockResource($mockParams);

        $this->assertNull($resources);
    }

    /**
     * 测试自定义回退重试
     */
    public function testFallbackRetry_WithCustomImplementation(): void
    {
        $procedure = new class extends LockableProcedure {
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
    public function testIdempotentCacheKey_WithCustomImplementation(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            protected function getIdempotentCacheKey(\Tourze\JsonRPC\Core\Model\JsonRpcRequest $request): ?string
            {
                return 'custom_cache_key';
            }

            public function mockGetIdempotentCacheKey(\Tourze\JsonRPC\Core\Model\JsonRpcRequest $request): ?string
            {
                return $this->getIdempotentCacheKey($request);
            }
        };

        /** @var JsonRpcRequest&MockObject $mockRequest */
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
        $container->set('Tourze\\JsonRPCLockBundle\\Procedure\\LockableProcedure::getSecurity', $security);
        return $container;
    }
}
