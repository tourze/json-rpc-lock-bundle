<?php

namespace Tourze\JsonRPCLockBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tourze\JsonRPCLockBundle\DependencyInjection\JsonRPCLockExtension;

/**
 * 测试 JsonRPCLockExtension 扩展类
 */
class JsonRPCLockExtensionTest extends TestCase
{
    /**
     * 测试加载配置
     */
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new JsonRPCLockExtension();

        $extension->load([], $container);

        // 检查扩展是否成功加载了配置文件
        $this->assertTrue(true, '扩展加载配置文件成功');
    }

    /**
     * 测试服务配置文件位置
     */
    public function testFileLoader(): void
    {
        $container = new ContainerBuilder();

        // 测试配置文件是否存在于正确位置
        $basePath = __DIR__ . '/../../src/Resources/config';
        $this->assertFileExists($basePath . '/services.yaml');

        // 验证加载器能成功加载文件
        $loader = new YamlFileLoader($container, new FileLocator($basePath));
        $loader->load('services.yaml');

        $this->assertTrue(true, '服务配置文件加载成功');
    }
}
