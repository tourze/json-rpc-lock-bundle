<?php

namespace Tourze\JsonRPCLockBundle\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCLockBundle\JsonRPCLockBundle;

/**
 * 测试 JsonRPCLockBundle 核心类
 */
class JsonRPCLockBundleTest extends TestCase
{
    /**
     * 测试 Bundle 类的基本实例化
     */
    public function testBundleInstantiation(): void
    {
        $bundle = new JsonRPCLockBundle();
        $this->assertInstanceOf(JsonRPCLockBundle::class, $bundle);
    }
}
