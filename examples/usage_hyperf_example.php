<?php
// 伪代码需要复制到 框架中运行
use Hyperf\Context\ApplicationContext;
use Wuwuseo\HibikenAsynqClient\Client;


$container = ApplicationContext::getContainer();

$redis = $container->get(\Hyperf\Redis\Redis::class);
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