<?php

namespace Tourze\JsonRPCLockBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class JsonRPCLockExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
