<?php
// 伪代码需要复制到 框架中运行
use Wuwuseo\HibikenAsynqClient\Client;

use think\facade\Cache;

// 注册成服务
//$container = app();
//$redis = $container->get(\Redis::class);

// 如果有配置缓存驱动 可以使用缓存的  操作句柄
$redis = Cache::store('redis')->handler();
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

echo "基本任务入队结果: " . ($result === 1 ? '成功' : '失败') . "\n";