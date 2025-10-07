# hibiken-asynq-client

[English Version](README_EN.md) | [中文版](README.md)

# Hibiken Asynq PHP Client

这是一个用于 [hibiken/asynq](https://github.com/hibiken/asynq) Go 任务队列的 PHP 客户端，用于在 PHP 中发送异步任务到 Asynq 队列。提供友好的 API、错误处理、中间件支持和性能优化。

## 什么是 Asynq?

Asynq 是一个 Go 语言库，用于将任务排队并通过工作线程异步处理它们。它由 Redis 支持，设计为可扩展且易于上手。

## 功能特性

- ✅ 简洁直观的 API 设计
- ✅ 流畅的任务选项配置（TaskOptions）
- ✅ 完整的错误处理和返回值系统
- ✅ 中间件支持（可扩展的任务处理流程）
- ✅ 唯一任务支持（防止重复执行）
- ✅ 延迟任务调度
- ✅ 任务分组功能
- ✅ 性能优化（Redis 键缓存、批量操作）
- ✅ 支持自定义 Redis 命名空间
- ✅ 完整的日志记录支持（兼容 PSR-3）

## 系统要求

```
    "php": "^8.1",
    "ext-redis": "^5.3 || ^6.0",
    "google/protobuf": "^3.24",
    "ramsey/uuid": "^4.7",
    "psr/log": "^1.1 || ^2.0 || ^3.0",
    "ext-bcmath": "*"
```

## 安装

使用 Composer 安装此包：

```bash
composer require wuwuseo/hibiken-asynq-client:dev-main
```


```bash
composer require wuwuseo/hibiken-asynq-client:1.1.2
```



## 基本使用

### 1. 创建客户端

```php
use Wuwuseo\HibikenAsynqClient\Client;

// 创建 Redis 连接
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// 创建基本客户端
$client = new Client($redis);
```

### 2. 发送基本任务

```php
$result = $client->enqueue(
    'email:send',  // 任务类型
    [              // 任务负载
        'to' => 'user@example.com',
        'subject' => '欢迎使用',
        'body' => '感谢您的注册！'
    ]
);

// 检查结果
if ($result === true) {
    echo '任务入队成功';
} else {
    echo '任务入队失败: ' . $client->getError();
}
```

### 3. 使用 TaskOptions 流畅配置

TaskOptions 提供了流畅的 API 来配置任务选项：

```php
use Wuwuseo\HibikenAsynqClient\TaskOptions;

// 配置任务选项
$options = (new TaskOptions())
    ->maxRetry(3)           // 最大重试次数
    ->timeout(60)           // 超时时间（秒）
    ->deadline(time() + 300) // 截止时间
    ->queue('priority')     // 队列名称
    ->build();              // 构建选项数组

// 入队配置了选项的任务
$result = $client->enqueue(
    'payment:process',
    ['order_id' => 'ORD-12345', 'amount' => 99.99],
    $options
);
```

### 4. 延迟任务

```php
// 延迟 5 分钟执行的任务
$result = $client->enqueueIn(
    'reminder:send',
    ['user_id' => 456, 'type' => 'appointment'],
    300 // 延迟时间（秒）
);
```

### 5. 唯一任务

防止在指定时间内重复执行相同的任务：

```php
$options = (new TaskOptions())
    ->uniqueTTL(3600) // 1 小时内保证唯一性
    ->build();

$result = $client->enqueue(
    'report:generate',
    ['report_type' => 'daily', 'date' => date('Y-m-d')],
    $options
);

// $result 可能的值：
// true: 任务成功入队
// 0: 任务已存在
// -1: 唯一键已存在
// false: 入队失败
```

### 6. 分组任务

将多个任务添加到同一个组：

```php
$groupId = 'batch_export_' . time();
$options = (new TaskOptions())
    ->group($groupId)
    ->build();

// 添加多个同组任务
for ($i = 1; $i <= 5; $i++) {
    $client->enqueue(
        'data:export',
        ['batch_id' => $groupId, 'record_id' => $i],
        $options
    );
}
```

### 7. 使用中间件

中间件可以拦截和修改任务数据，实现横切关注点：

```php
use Psr\Log\LoggerInterface;

// 添加日志中间件
$client->addMiddleware(function ($taskData, $next) {
    // 在任务处理前执行
    $startTime = microtime(true);
    
    // 调用下一个中间件或最终处理函数
    $result = $next($taskData);
    
    // 在任务处理后执行
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    echo "任务 '{$taskData['type']}' 处理耗时: {$duration}秒\n";
    
    return $result;
});

// 添加认证中间件示例
$client->addMiddleware(function ($taskData, $next) {
    // 检查是否有权限发送特定类型的任务
    if (str_starts_with($taskData['type'], 'admin:')) {
        // 验证逻辑
        if (!hasAdminPermission()) {
            throw new \Exception('无权限发送管理员任务');
        }
    }
    return $next($taskData);
});
```

### 8. 配置日志记录

客户端支持 PSR-3 兼容的日志记录器：

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 创建日志记录器
$logger = new Logger('asynq-client');
$logger->pushHandler(new StreamHandler('asynq.log', Logger::DEBUG));

// 创建带日志的客户端
$client = new Client($redis, $logger);

// 客户端现在会记录所有操作
```

### 9. 自定义 Redis 命名空间
#### 注意事项
需要同步修改 go 源码的前缀  截止到写下这句话 为止  go 命名空间是固定的写死的
#### 示例

```php
// 在构造时设置命名空间
$client = new Client($redis, null, 'my_application_namespace');

// 或者之后设置
$client->setNamespace('my_application_namespace');
```

## 性能优化

优化版客户端包含多项性能改进：

1. **Redis 键缓存** - 缓存常用的 Redis 键，减少字符串拼接操作
2. **Lua 脚本支持** - 使用 Redis lua脚本确保任务操作的原子性
3. **错误处理** - 完善的异常捕获和资源清理机制
4. **灵活配置** - 支持自定义超时、重试策略等

## 错误处理

```php
// 检查错误信息
if (!$result) {
    $error = $client->getError();
    echo "任务处理失败: {$error}\n";
}

// 也可以捕获异常
try {
    $result = $client->enqueue('task:type', ['data' => 'value']);
} catch (\Exception $e) {
    echo "捕获到异常: {$e->getMessage()}\n";
}
```

## 返回值说明

`enqueue` 和 `enqueueIn` 方法可能返回以下值：

- `true`: 任务成功入队
- `0`: 任务已存在（已在队列中）
- `-1`: 唯一键已存在（对于唯一任务）
- `false`: 入队失败，可通过 `getError()` 获取错误信息

## 完整示例

### PHP 客户端示例

查看 `examples/usage_example.php` 文件获取完整的 PHP 客户端使用示例，包括所有功能的演示。

### Go Worker 示例

项目还包含一个 Go 语言的工作线程示例，用于处理由 PHP 客户端发送的任务：

- 位置：`examples/worker/main.go`
- 功能：支持处理多种任务类型，包括 email:send、notification:push、reminder:send 等
- 特性：包含详细的日志记录、错误处理和任务解析功能

## 运行示例

### PHP 客户端示例

确保 Redis 服务已启动，然后运行：

```bash
php examples/usage_example.php
```

### Go Worker 示例

要运行 Go 工作线程示例，请先确保已安装 Go 环境并设置了正确的依赖，然后执行：

```bash
# 在项目根目录下执行
# 如果缺少 go.mod 文件，创建它
# echo "module github.com/example/asynq-client\ngo 1.25.1\nrequire github.com/hibiken/asynq v0.25.1" > go.mod
# 安装依赖
# go get github.com/hibiken/asynq@v0.25.1
# 运行 worker 示例
go run -mod=mod .\examples\worker\main.go
```

运行成功后，worker 将开始监听 Redis 队列并处理任务，终端将显示类似以下输出：

```
asynq: pid=54716 2025/10/01 14:00:15.225112 INFO: Starting processing
asynq: pid=54716 2025/10/01 14:00:15.225754 INFO: Send signal TERM or INT to terminate the process
```

## 开发和贡献

欢迎提交 Issue 和 Pull Request 来改进这个项目。

## 许可证

本项目采用 MIT 许可证。
