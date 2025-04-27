<?php

namespace Tourze\JsonRPCLockBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;

class JsonRPCLockBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();
        Backtrace::addProdIgnoreFiles((new \ReflectionClass(LockableProcedure::class))->getFileName());
    }
}
