<?php

namespace Tourze\JsonRPCLockBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;

class JsonRPCLockBundle extends Bundle implements BundleDependencyInterface
{
    public function boot(): void
    {
        parent::boot();
        Backtrace::addProdIgnoreFiles((new \ReflectionClass(LockableProcedure::class))->getFileName());
    }

    public static function getBundleDependencies(): array
    {
        return [
            \Tourze\LockServiceBundle\LockServiceBundle::class => ['all' => true],
        ];
    }
}
