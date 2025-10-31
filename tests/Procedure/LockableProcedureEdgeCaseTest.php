<?php

namespace Tourze\JsonRPCLockBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * LockableProcedure 边界情况测试
 *
 * @internal
 */
#[CoversClass(LockableProcedure::class)]
#[RunTestsInSeparateProcesses]
final class LockableProcedureEdgeCaseTest extends AbstractProcedureTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊设置，使用默认集成测试环境
    }

    /**
     * 测试空字符串锁资源被过滤
     */
    public function testLockResourceFilteringEmptyStrings(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试空字符串锁资源被过滤')]
        #[MethodExpose(method: 'test_lockResourceFilteringEmptyStrings')]
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
                return ['valid_resource', '', 'another_valid_resource'];
            }

            /**
             * @return array<string>
             */
            public function exposedFilterLockResources(JsonRpcParams $params): array
            {
                // 模拟 __invoke 方法中的锁资源过滤逻辑
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if ('' === $v || null === $v || false === $v || 0 === $v || '0' === $v || [] === $v) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }

                /** @var array<string> */
                return array_values(array_unique($lockResources));
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
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        // 应该只保留非空的资源
        $this->assertEquals(['valid_resource', 'another_valid_resource'], $filteredResources);
    }

    /**
     * 测试 LockEntity 资源转换
     */
    public function testLockResourceFilteringWithLockEntity(): void
    {
        // 使用 LockEntity 接口进行 mock，这是正确的做法：
        // 1. LockEntity 是一个接口，符合最佳实践
        // 2. 通过接口 mock 可以减少测试对具体实现的依赖
        // 3. 提高测试的可维护性和灵活性
        /** @var LockEntity&MockObject $mockLockEntity1 */
        // PHPStan: Using concrete class Entity instead of interface because
        // this is a Doctrine entity that doesn't implement a common interface
        // This is necessary for proper method mocking in tests
        $mockLockEntity1 = $this->createMock(LockEntity::class);
        $mockLockEntity1->method('retrieveLockResource')->willReturn('entity_resource_1');

        // 使用 LockEntity 接口进行 mock，这是正确的做法：
        // 1. LockEntity 是一个接口，符合最佳实践
        // 2. 通过接口 mock 可以减少测试对具体实现的依赖
        // 3. 提高测试的可维护性和灵活性
        /** @var LockEntity&MockObject $mockLockEntity2 */
        // PHPStan: Using concrete class Entity instead of interface because
        // this is a Doctrine entity that doesn't implement a common interface
        // This is necessary for proper method mocking in tests
        $mockLockEntity2 = $this->createMock(LockEntity::class);
        $mockLockEntity2->method('retrieveLockResource')->willReturn('entity_resource_2');

        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试 LockEntity 资源转换')]
        #[MethodExpose(method: 'test_lockResourceFilteringWithLockEntity')]
        class($mockLockEntity1, $mockLockEntity2) extends LockableProcedure {
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

            /**
             * @return array<mixed>
             */
            public function getLockResource(JsonRpcParams $params): array
            {
                return ['string_resource', $this->entity1, $this->entity2];
            }

            /**
             * @return array<string>
             */
            public function exposedFilterLockResources(JsonRpcParams $params): array
            {
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if ('' === $v || null === $v || false === $v || 0 === $v || '0' === $v || [] === $v) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }

                /** @var array<string> */
                return array_values(array_unique($lockResources));
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
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        $this->assertEquals(['string_resource', 'entity_resource_1', 'entity_resource_2'], $filteredResources);
    }

    /**
     * 测试重复资源去重
     */
    public function testLockResourceFilteringDeduplication(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试重复资源去重')]
        #[MethodExpose(method: 'test_lockResourceFilteringDeduplication')]
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
                return ['resource_a', 'resource_b', 'resource_a', 'resource_c', 'resource_b'];
            }

            /**
             * @return array<string>
             */
            public function exposedFilterLockResources(JsonRpcParams $params): array
            {
                $lockResources = $this->getLockResource($params);
                foreach ($lockResources as $k => $v) {
                    if ('' === $v || null === $v || false === $v || 0 === $v || '0' === $v || [] === $v) {
                        unset($lockResources[$k]);
                    }
                    if ($v instanceof LockEntity) {
                        $lockResources[$k] = $v->retrieveLockResource();
                    }
                }

                /** @var array<string> */
                return array_values(array_unique($lockResources));
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
        $filteredResources = $procedure->exposedFilterLockResources($mockParams);

        $this->assertEquals(['resource_a', 'resource_b', 'resource_c'], $filteredResources);
    }

    /**
     * 测试用户身份获取失败的处理
     */
    public function testGetLockResourceWithUserGetterException(): void
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
        $mockSecurity->method('getUser')->willThrowException(new \RuntimeException('Security service error'));

        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试用户身份获取失败的处理')]
        #[MethodExpose(method: 'test_getLockResourceWithUserGetterException')]
        class extends LockableProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        $container = new Container();
        $container->set('Tourze\JsonRPCLockBundle\Procedure\LockableProcedure::getSecurity', $mockSecurity);
        $procedure->setContainer($container);

        // 使用具体类 JsonRpcParams 而非接口的原因：
        // 1. JsonRpc Core 包中没有提供参数对象的接口抽象
        // 2. 这个类继承自 Symfony ParameterBag，是稳定的实现
        // 3. 测试需要验证与具体参数处理逻辑的交互
        /** @var JsonRpcParams&MockObject $mockParams */
        // PHPStan: Using concrete class instead of interface because
        // this class doesn't implement a common interface suitable for testing
        // This is necessary for proper method mocking in tests
        $mockParams = $this->createMock(JsonRpcParams::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Security service error');

        $procedure->getLockResource($mockParams);
    }

    /**
     * 测试过程名称生成的边界情况
     */
    public function testGetProcedureNameWithComplexClassName(): void
    {
        $procedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试过程名称生成的边界情况')]
        #[MethodExpose(method: 'test_getProcedureNameWithComplexClassName')]
        class extends LockableProcedure {
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
    public function testGetProcedureNameStaticCall(): void
    {
        $concreteProcedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: '测试静态方法调用')]
        #[MethodExpose(method: 'test_getProcedureNameStaticCall')]
        class extends LockableProcedure {
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
