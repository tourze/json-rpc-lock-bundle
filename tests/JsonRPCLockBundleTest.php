<?php

namespace Tourze\JsonRPCLockBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCLockBundle\JsonRPCLockBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCLockBundle::class)]
#[RunTestsInSeparateProcesses]
final class JsonRPCLockBundleTest extends AbstractBundleTestCase
{
}
