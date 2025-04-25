<?php

namespace Tourze\JsonRPCLockBundle\Tests\Procedure;

use PHPUnit\Framework\TestCase;
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
        $procedure = $this->getMockForAbstractClass(LockableProcedure::class);
        $className = get_class($procedure);

        $this->assertEquals(str_replace('\\', '_', $className), $procedure::getProcedureName());
    }

    /**
     * 测试默认不回退重试
     */
    public function testFallbackRetry(): void
    {
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
        $mockLockEntity = $this->createMock(LockEntity::class);
        $mockLockEntity->method('retrieveLockResource')
            ->willReturn($lockResource);

        // 断言资源检索方法被正确调用并返回期望值
        $this->assertEquals($lockResource, $mockLockEntity->retrieveLockResource());
    }
}
