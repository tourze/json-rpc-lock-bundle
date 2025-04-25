# JsonRPCLockBundle 测试计划

## 单元测试覆盖情况

- [x] 基本 Bundle 类测试
- [x] LockableProcedure 基本功能测试
  - [x] 过程名称生成
  - [x] 默认幂等缓存键
  - [x] 回退重试
  - [x] LockEntity 资源检索
- [x] DependencyInjection 扩展测试
  - [x] 配置加载
  - [x] 服务加载

## 未覆盖项目

- [ ] 调用过程中的具体锁行为测试（需要集成环境）
- [ ] 与实际 Symfony 应用的集成测试

## 测试注意事项

由于该包与 Symfony 容器、安全组件和锁服务等紧密集成，要完全覆盖所有测试场景需要更完整的集成测试环境，这部分无法在纯单元测试中完全实现。当前的测试主要针对可以独立测试的功能进行验证。

## 测试执行

测试使用标准 PHPUnit 进行，通过以下命令执行：

```bash
./vendor/bin/phpunit packages/json-rpc-lock-bundle/tests
```
