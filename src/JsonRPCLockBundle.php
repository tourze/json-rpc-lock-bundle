<?php

namespace Tourze\JsonRPCLockBundle;

use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\LockServiceBundle\LockServiceBundle;

class JsonRPCLockBundle extends Bundle implements BundleDependencyInterface
{
    public function boot(): void
    {
        parent::boot();
        $filename = (new \ReflectionClass(LockableProcedure::class))->getFileName();
        if (false !== $filename) {
            Backtrace::addProdIgnoreFiles($filename);
        }
    }

    public static function getBundleDependencies(): array
    {
        return [
            LockServiceBundle::class => ['all' => true],
            SecurityBundle::class => ['all' => true],
        ];
    }
}
