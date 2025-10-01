<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Wuwuseo\HibikenAsynqClient\Client;
use Wuwuseo\HibikenAsynqClient\TaskOptions;

// 1. 基础连接与配置
function basicConnection()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建基本客户端
    $client = new Client($redis);
    
    // 入队一个简单任务
    $result = $client->enqueue(
        'email:send',
        [
            'to' => 'user@example.com',
            'subject' => 'Welcome!',
            'body' => 'Thank you for registering.'
        ]
    );
    
    echo "基本任务入队结果: " . ($result ? '成功' : '失败') . "\n";
}

// 2. 使用日志记录器
function withLogger()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 检查 Monolog 类是否可用
    if (class_exists('Monolog\Logger')) {
        // 创建日志记录器
        $logger = new \Monolog\Logger('asynq-client');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler('asynq.log', \Monolog\Logger::DEBUG));
        
        // 创建带日志的客户端
        $client = new Client($redis, $logger);
        
        // 入队任务
        $result = $client->enqueue(
            'notification:push',
            [
                'user_id' => 123,
                'message' => 'Your order has been shipped!'
            ]
        );
        
        echo "带日志任务入队结果: " . ($result ? '成功' : '失败') . "\n";
        echo "查看 asynq.log 文件了解更多详情\n";
    } else {
        // Monolog 不可用时提供友好的错误消息
        echo "警告: Monolog 库不可用。要启用日志功能，请运行:\n";
        echo "composer require monolog/monolog\n";
        echo "当前将使用无日志客户端继续执行\n";
        
        // 使用无日志客户端
        $client = new Client($redis);
        
        // 入队任务
        $result = $client->enqueue(
            'notification:push',
            [
                'user_id' => 123,
                'message' => 'Your order has been shipped!'
            ]
        );
        
        echo "无日志任务入队结果: " . ($result ? '成功' : '失败') . "\n";
    }
}

// 3. 使用 TaskOptions 流畅 API
function withTaskOptions()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建客户端
    $client = new Client($redis);
    
    // 使用流畅的 TaskOptions API 配置任务
    $options = (new TaskOptions())
        ->maxRetry(5)
        ->timeout(60)
        ->queue('priority')
        ->toArray();
    
    // 入队任务
    $result = $client->enqueue(
        'payment:process',
        [
            'order_id' => 'ORD-12345',
            'amount' => 99.99,
            'currency' => 'USD'
        ],
        $options
    );
    
    echo "使用 TaskOptions 入队结果: " . ($result ? '成功' : '失败') . "\n";
}

// 4. 延迟任务
function delayedTask()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建客户端
    $client = new Client($redis);
    
    // 延迟 5 分钟执行的任务
    $result = $client->enqueueIn(
        'reminder:send',
        [
            'user_id' => 456,
            'type' => 'appointment',
            'time' => '2023-12-25 10:00:00'
        ],
        300 // 5分钟 = 300秒
    );
    
    echo "延迟任务入队结果: " . ($result ? '成功' : '失败') . "\n";
}

// 5. 唯一任务
function uniqueTask()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建客户端
    $client = new Client($redis);
    
    // 配置唯一性任务，有效期 1 小时
    $options = (new TaskOptions())
        ->queue('reports')
        ->unique(3600) // 1小时 = 3600秒
        ->toArray();
    
    // 入队唯一任务
    $result = $client->enqueue(
        'report:generate',
        [
            'report_type' => 'daily_sales',
            'date' => date('Y-m-d')
        ],
        $options
    );

    echo "唯一任务入队结果: " . ($result !== -1 ? '成功' : '失败') . "\n";
    echo "(如果在 1 小时内再次运行此代码，将返回唯一键已存在的错误)\n";
}

// 6. 分组任务
function groupedTask()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建客户端
    $client = new Client($redis);
    
    // 创建多个同组任务
    $groupId = 'batch_export_' . date('YmdHis');
    
    // 配置分组任务
    $options = (new TaskOptions())
        ->queue('exports')
        ->group($groupId)
        ->toArray();
    
    // 入队多个同组任务
    for ($i = 1; $i <= 3; $i++) {
        $result = $client->enqueue(
            'data:export',
            [
                'batch_id' => $groupId,
                'record_id' => $i,
                'format' => 'csv'
            ],
            $options
        );
        
        echo "分组任务 #{$i} 入队结果: " . ($result ? '成功' : '失败') . "\n";
    }
    
    echo "所有任务已添加到组: {$groupId}\n";
}

// 7. 使用中间件
function withMiddleware()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建客户端
    $client = new Client($redis);
    
    // 添加日志中间件
    $client->addMiddleware(function ($taskData, $next) {
        echo "中间件: 处理任务类型 '{$taskData['type']}'\n";
        // 可以在这里修改任务数据
        $taskData['options']['processed_by_middleware'] = true;
        // 继续执行
        return $next($taskData);
    });
    
    // 入队任务
    $result = $client->enqueue(
        'analytics:track',
        [
            'event' => 'page_view',
            'user_id' => 789,
            'page' => '/dashboard'
        ]
    );
    
    echo "带中间件任务入队结果: " . ($result ? '成功' : '失败') . "\n";
}

// 8. 自定义命名空间
function customNamespace()
{
    // 创建 Redis 连接
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // 创建使用自定义命名空间的客户端
    $client = new Client($redis, null, 'my_app_asynq');
    
    // 或者使用 setter 方法
    // $client->setNamespace('my_app_asynq');
    
    // 入队任务
    $result = $client->enqueue(
        'maintenance:cleanup',
        [
            'type' => 'cache',
            'ttl' => 86400
        ]
    );
    
    echo "自定义命名空间任务入队结果: " . ($result ? '成功' : '失败') . "\n";
    echo "Redis 键将使用 'my_app_asynq' 前缀\n";
}

// 运行所有示例
function runAllExamples()
{
    echo "===== Asynq 客户端优化版使用示例 =====\n\n";
    
    try {
        echo "\n[1] 基础连接示例\n";
        basicConnection();
        
        echo "\n[2] 使用日志记录器示例\n";
        withLogger();
        
        echo "\n[3] 使用 TaskOptions 流畅 API 示例\n";
        withTaskOptions();
        
        echo "\n[4] 延迟任务示例\n";
        delayedTask();
        
        echo "\n[5] 唯一任务示例\n";
        uniqueTask();
        
        echo "\n[6] 分组任务示例\n";
        groupedTask();
        
        echo "\n[7] 使用中间件示例\n";
        withMiddleware();
        
        echo "\n[8] 自定义命名空间示例\n";
        customNamespace();
        
        echo "\n===== 所有示例运行完毕 =====\n";
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        echo "请确保 Redis 服务已启动并可访问\n";
    }
}

// 执行示例
runAllExamples();