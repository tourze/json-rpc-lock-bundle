<?php

namespace Tourze\JsonRPCLockBundle\Tests\Procedure;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * LockableProcedure 边界情况测试
 */
class LockableProcedureEdgeCaseTest extends TestCase
{
    /**
     * 测试空字符串锁资源被过滤
     */
    public function testLockResourceFiltering_EmptyStrings(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function getLockResource(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): ?array
            {
                return ['valid_resource', '', null, 'another_valid_resource', 0];
            }

            public function exposedFilterLockResources(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): array
            {
                // 模拟 __invoke 方法中的锁资源过滤逻辑
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if (empty($v)) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }
                return array_values(array_unique($lockResources));
            }
        };

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        // 应该只保留非空的资源
        $this->assertEquals(['valid_resource', 'another_valid_resource'], $filteredResources);
    }

    /**
     * 测试 LockEntity 资源转换
     */
    public function testLockResourceFiltering_WithLockEntity(): void
    {
        /** @var LockEntity&MockObject $mockLockEntity1 */
        $mockLockEntity1 = $this->createMock(LockEntity::class);
        $mockLockEntity1->method('retrieveLockResource')->willReturn('entity_resource_1');

        /** @var LockEntity&MockObject $mockLockEntity2 */
        $mockLockEntity2 = $this->createMock(LockEntity::class);
        $mockLockEntity2->method('retrieveLockResource')->willReturn('entity_resource_2');

        $procedure = new class($mockLockEntity1, $mockLockEntity2) extends LockableProcedure {
            private LockEntity $entity1;
            private LockEntity $entity2;

            public function __construct(LockEntity $entity1, LockEntity $entity2)
            {
                $this->entity1 = $entity1;
                $this->entity2 = $entity2;
            }

            public function execute(): array
            {
                return [];
            }

            public function getLockResource(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): ?array
            {
                return ['string_resource', $this->entity1, $this->entity2];
            }

            public function exposedFilterLockResources(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): array
            {
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if (empty($v)) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }
                return array_values(array_unique($lockResources));
            }
        };

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        $this->assertEquals(['string_resource', 'entity_resource_1', 'entity_resource_2'], $filteredResources);
    }

    /**
     * 测试重复资源去重
     */
    public function testLockResourceFiltering_Deduplication(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }

            public function getLockResource(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): ?array
            {
                return ['resource_a', 'resource_b', 'resource_a', 'resource_c', 'resource_b'];
            }

            public function exposedFilterLockResources(\Tourze\JsonRPC\Core\Model\JsonRpcParams $params): array
            {
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if (empty($v)) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }
                return array_values(array_unique($lockResources));
            }
        };

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        $this->assertEquals(['resource_a', 'resource_b', 'resource_c'], $filteredResources);
    }

    /**
     * 测试用户身份获取失败的处理
     */
    public function testGetLockResource_WithUserGetterException(): void
    {
        /** @var Security&MockObject $mockSecurity */
        $mockSecurity = $this->createMock(Security::class);
        $mockSecurity->method('getUser')->willThrowException(new \RuntimeException('Security service error'));

        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        $container = new Container();
        $container->set('Tourze\\JsonRPCLockBundle\\Procedure\\LockableProcedure::getSecurity', $mockSecurity);
        $procedure->setContainer($container);

        /** @var JsonRpcParams&MockObject $mockParams */
        $mockParams = $this->createMock(JsonRpcParams::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Security service error');

        $procedure->getLockResource($mockParams);
    }

    /**
     * 测试过程名称生成的边界情况
     */
    public function testGetProcedureName_WithComplexClassName(): void
    {
        $procedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        $procedureName = $procedure::getProcedureName();
        
        // 验证命名空间分隔符被正确替换
        $this->assertStringNotContainsString('\\', $procedureName);
        $this->assertStringContainsString('_', $procedureName);
    }

    /**
     * 测试静态方法调用
     */
    public function testGetProcedureName_StaticCall(): void
    {
        $concreteProcedure = new class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        $className = get_class($concreteProcedure);
        $expectedName = str_replace('\\', '_', $className);

        $this->assertEquals($expectedName, $concreteProcedure::getProcedureName());
    }
} 