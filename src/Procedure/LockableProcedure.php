<?php

namespace Tourze\JsonRPCLockBundle\Procedure;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\LockServiceBundle\Model\LockEntity;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * 自动加锁的JsonRPC请求
 *
 * 对于有写操作、耗时、外部请求的方法，我们尽可能加锁来减少问题
 */
abstract class LockableProcedure extends BaseProcedure implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService]
    private function getLockService(): LockService
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    protected function getLockLogger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    private function getSecurity(): Security
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    private function getCache(): CacheInterface
    {
        return $this->container->get(__METHOD__);
    }

    public static function getProcedureName(): string
    {
        return str_replace('\\', '_', static::class);
    }

    /**
     * 获取锁资源、锁名
     * 有登录的话，返回的是接口名+用户标志，否则就只有接口名
     * 要注意，这里我们不应该把参数信息加入到锁中去
     * 如果返回null，则说明跳过加锁逻辑
     */
    protected function getLockResource(JsonRpcParams $params): ?array
    {
        // TODO 是否可以在 property 上面加注解来声明要不要加锁呢？

        // 如果当前用户有登录的话，那就按照用户维护来加锁
        if ($this->getSecurity()->getUser()) {
            return [
                $this->getSecurity()->getUser()->getUserIdentifier(),
            ];
        }

        return [
            static::getProcedureName(),
        ];
    }

    public function fallbackRetry(): bool
    {
        return false;
    }

    /**
     * 幂等缓存key
     */
    protected function getIdempotentCacheKey(JsonRpcRequest $request): ?string
    {
        return null;
    }

    private function getIdempotentCache(JsonRpcRequest $request): mixed
    {
        $cacheKey = $this->getIdempotentCacheKey($request);

        $res = $this->getCache()->get($cacheKey, function (ItemInterface $item) {
            if ($item->isHit()) {
                return $item->get();
            }
            return false;
        });

        if ($res !== false) {
            return $res;
        }
        return null;
    }

    public function __invoke(JsonRpcRequest $request): mixed
    {
        $lockResources = $this->getLockResource($request->getParams());
        foreach ($lockResources as $k => $v) {
            if (empty($v)) {
                unset($lockResources[$k]);
            }
            if ($v instanceof LockEntity) {
                $lockResources[$k] = $v->retrieveLockResource();
            }
        }
        if (empty($lockResources)) {
            return parent::__invoke($request);
        }
        $lockResources = array_values(array_unique($lockResources));

        $res = $this->getIdempotentCache($request);
        if ($res !== null) {
            return $res;
        }

        try {
            $res = $this->getLockService()->blockingRun($lockResources, fn() => parent::__invoke($request));
            $cacheKey = $this->getIdempotentCacheKey($request);
            if ($cacheKey !== null) {
                $this->getCache()->get($cacheKey, function (ItemInterface $item) use ($res) {
                    $item->set($res);
                    $item->expiresAfter(60); // 默认1分钟缓存
                    return $item->get();
                });
            }
            return $res;
        } catch (LockConflictedException $exception) {
            $res = $this->getIdempotentCache($request);
            if ($res !== null) {
                return $res;
            }

            throw new ApiException($_ENV['JSON_RPC_RESPONSE_EXCEPTION_MASSAGE'] ?? '你手速太快了，请稍候', previous: $exception);
        } catch (LockAcquiringException $exception) {
            $res = $this->getIdempotentCache($request);
            if ($res !== null) {
                return $res;
            }

            throw new ApiException($_ENV['JSON_RPC_RESPONSE_EXCEPTION_MASSAGE'] ?? '你手速太快了，请稍等', previous: $exception);
        } catch (\Throwable $exception) {
            if ($exception instanceof ApiException) {
                throw $exception;
            }

            // 也可能出现 Symfony\Component\Lock\Exception\LockExpiredException 锁过期的问题
            // 其他异常，一般就是 RedisException 那种咯，这种情况，意味着我们某个组件出问题了，为了减少消费者侧的困惑，直接执行吧
            $this->getLockLogger()->error('Procedure执行锁操作时发生异常', [
                'exception' => $exception,
                'params' => $request->getParams(),
                'className' => static::class,
            ]);

            if ($this->fallbackRetry()) {
                return parent::__invoke($request);
            }
            throw $exception;
        }
    }
}
