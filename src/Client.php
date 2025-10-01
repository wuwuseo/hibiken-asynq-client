<?php

namespace Wuwuseo\HibikenAsynqClient;

use Ramsey\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client
{
    /**
     * @var Rdb
     */
    protected $broker;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected string $error = '';

    protected int $defaultTimeout = 3600;

    protected int $defaultMaxRetry = 25;

    protected array $middlewares = [];

    /**
     * 构造函数
     * 
     * @param \Redis $redis Redis 实例
     * @param LoggerInterface|null $logger 日志记录器
     * @param string $namespace Redis 命名空间
     */
    public function __construct(\Redis $redis, LoggerInterface $logger = null, string $namespace = 'asynq')
    {
        $this->broker = new Rdb($redis, $namespace);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 获取错误消息
     * 
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 设置默认超时时间
     * 
     * @param int $timeout 超时时间（秒）
     * @return $this
     */
    public function setDefaultTimeout(int $timeout): self
    {
        $this->defaultTimeout = $timeout;
        return $this;
    }

    /**
     * 设置默认最大重试次数
     * 
     * @param int $maxRetry 最大重试次数
     * @return $this
     */
    public function setDefaultMaxRetry(int $maxRetry): self
    {
        $this->defaultMaxRetry = $maxRetry;
        return $this;
    }

    /**
     * 添加中间件
     * 
     * @param callable $middleware 中间件回调函数
     * @return $this
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * 组合选项参数
     * 
     * @param array $options 选项数组
     * @return array 组合后的选项
     */
    protected function composeOptions(array $options = []): array
    {
        $option = [
            'retry' => $this->defaultMaxRetry,
            'queue' => 'default',
            'taskID' => (string)Uuid::uuid4(),
            'timeout' => 0,
            'deadline' => 0,
            'processAt' => time(),
        ];

        // 清理和验证选项
        if (isset($options['queue'])) {
            $queue = trim($options['queue']);
            if (!empty($queue)) {
                $options['queue'] = $queue;
            } else {
                unset($options['queue']);
            }
        }

        if (isset($options['taskID']) && empty($options['taskID'])) {
            unset($options['taskID']);
        }

        if (isset($options['uniqueTTL']) && ($options['uniqueTTL'] < 1)) {
            unset($options['uniqueTTL']);
        }

        if (isset($options['group']) && empty($options['group'])) {
            unset($options['group']);
        }

        return array_merge($option, $options);
    }

    /**
     * 入队任务
     * 
     * @param string $type 任务类型
     * @param array $payload 任务负载
     * @param array $options 任务选项
     * @return int|bool 成功返回 true，失败返回错误代码或 false
     */
    public function enqueue(string $type, array $payload, array $options = []): int|bool
    {
        try {
            // 验证参数
            if (empty(trim($type))) {
                throw new \InvalidArgumentException('Task type cannot be empty');
            }

            if (empty($payload)) {
                throw new \InvalidArgumentException('Task payload cannot be empty');
            }

            $this->logger->info('Enqueue task', ['type' => $type]);

            // 应用中间件
            $taskData = [
                'type' => $type,
                'payload' => $payload,
                'options' => $options
            ];

            foreach ($this->middlewares as $middleware) {
                $taskData = $middleware($taskData, function($data) {
                    return $data;
                });
            }

            $type = $taskData['type'];
            $payload = $taskData['payload'];
            $options = $taskData['options'];

            $opts = $this->composeOptions($options);
            $payloadJson = json_encode($payload);

            if ($payloadJson === false) {
                throw new \RuntimeException('Failed to encode payload to JSON: ' . json_last_error_msg());
            }

            $timeout = 0;
            if ($opts['timeout'] > 0) {
                $timeout = $opts['timeout'];
            }

            if ($opts['deadline'] === 0 && $timeout === 0) {
                $timeout = $this->defaultTimeout;
            }

            $ttl = 0;
            $uniqueKey = null;
            if (isset($opts['uniqueTTL']) && $opts['uniqueTTL'] > 0) {
                $uniqueKey = $this->broker->uniqueKey($opts['queue'], $type, $payloadJson);
                $ttl = $opts['uniqueTTL'];
            }

            $msg = new TaskMessage();
            $msg->setId($opts['taskID']);
            $msg->setType($type);
            $msg->setPayload($payloadJson);
            $msg->setQueue($opts['queue']);
            $msg->setRetry($opts['retry']);
            $msg->setDeadline($opts['deadline']);
            $msg->setTimeout($timeout);

            if (isset($uniqueKey)) {
                $msg->setUniqueKey($uniqueKey);
            }

            if (isset($opts['group'])) {
                $msg->setGroupKey($opts['group']);
            }

            if (isset($opts['retention'])) {
                $msg->setRetention($opts['retention']);
            }

            $now = time();

            if ($opts['processAt'] > $now) {
                $result = $this->schedule($msg, $opts['processAt'], $ttl);
            } else if (isset($opts['group']) && !empty($opts['group'])) {
                $result = $this->addToGroup($msg, $opts['group'], $ttl);
            } else {
                $result = $this->enqueueDo($msg, $ttl);
            }

            if ($result === true) {
                $this->logger->info('Task enqueued successfully', ['task_id' => $opts['taskID'], 'queue' => $opts['queue']]);
            } else {
                $this->logger->warning('Failed to enqueue task', ['result' => $result]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->logger->error('Error enqueuing task', ['error' => $this->error, 'type' => $type]);
            return false;
        }
    }

    /**
     * 延迟入队任务
     * 
     * @param string $type 任务类型
     * @param array $payload 任务负载
     * @param int $delay 延迟时间（秒）
     * @param array $options 任务选项
     * @return int|bool 成功返回 true，失败返回错误代码或 false
     */
    public function enqueueIn(string $type, array $payload, int $delay, array $options = []): int|bool
    {
        if ($delay <= 0) {
            throw new \InvalidArgumentException('Delay must be positive');
        }

        $options['processAt'] = time() + $delay;
        return $this->enqueue($type, $payload, $options);
    }

    /**
     * 调度任务
     * 
     * @param TaskMessage $msg 任务消息
     * @param int $processAt 处理时间
     * @param int $uniqueTTL 唯一性过期时间
     * @return bool|int 结果
     */
    protected function schedule(TaskMessage $msg, int $processAt, int $uniqueTTL = 0): bool|int
    {
        if ($uniqueTTL > 0) {
            return $this->broker->scheduleUnique($msg, $processAt, $uniqueTTL);
        }
        return $this->broker->schedule($msg, $processAt);
    }

    /**
     * 执行入队
     * 
     * @param TaskMessage $msg 任务消息
     * @param int $uniqueTTL 唯一性过期时间
     * @return bool|int 结果
     */
    protected function enqueueDo(TaskMessage $msg, int $uniqueTTL = 0): bool|int
    {
        if ($uniqueTTL > 0) {
            return $this->broker->enqueueUnique($msg, $uniqueTTL);
        }
        return $this->broker->enqueue($msg);
    }

    /**
     * 添加到任务组
     * 
     * @param TaskMessage $msg 任务消息
     * @param string $group 组名
     * @param int $uniqueTTL 唯一性过期时间
     * @return bool|int 结果
     */
    protected function addToGroup(TaskMessage $msg, string $group, int $uniqueTTL = 0)
    {
        if ($uniqueTTL > 0) {
            return $this->broker->addToGroupUnique($msg, $group, $uniqueTTL);
        }
        return $this->broker->addToGroup($msg, $group);
    }

    /**
     * 获取 Rdb 实例
     * 
     * @return Rdb
     */
    public function getBroker(): Rdb
    {
        return $this->broker;
    }

    /**
     * 设置 Redis 命名空间
     * 
     * @param string $namespace 命名空间
     * @return $this
     */
    public function setNamespace(string $namespace): self
    {
        $this->broker->setNamespace($namespace);
        return $this;
    }
}