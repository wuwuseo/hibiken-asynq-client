<?php

namespace Wuwuseo\HibikenAsynqClient;

/**
 * OptimizedRdb 类提供优化的 Redis 操作接口
 */
class OptimizedRdb
{
    /**
     * @var \Redis Redis 实例
     */
    protected \Redis $redis;

    /**
     * @var string Redis 键前缀
     */
    protected string $namespace = 'asynq';

    /**
     * @var string 队列列表键
     */
    protected string $queuesKey = 'asynq:queues';

    /**
     * @var array 缓存的键值
     */
    protected array $keyCache = [];

    /**
     * 构造函数
     * 
     * @param \Redis $redis Redis 实例
     * @param string $namespace 命名空间
     */
    public function __construct(\Redis $redis, string $namespace = 'asynq')
    {
        $this->redis = $redis;
        $this->namespace = $namespace;
        $this->queuesKey = "{$namespace}:queues";
    }

    /**
     * 生成纳秒级时间戳
     * 
     * @return string 纳秒时间戳
     */
    protected function nanoseconds(): string
    {
        [$nanoSeconds, $seconds] = explode(' ', microtime());
        return bcmul((string)($seconds + $nanoSeconds), '1000000000');
    }

    /**
     * 生成唯一键
     * 
     * @param string $qname 队列名称
     * @param string $tasktype 任务类型
     * @param string $payload 任务负载
     * @return string 唯一键
     */
    public function uniqueKey(string $qname, string $tasktype, string $payload): string
    {
        $cacheKey = "unique:{$qname}:{$tasktype}:" . (empty($payload) ? 'empty' : md5($payload));
        
        if (!isset($this->keyCache[$cacheKey])) {
            if (empty($payload)) {
                $this->keyCache[$cacheKey] = sprintf('%sunique:%s:', $this->queueKeyPrefix($qname), $tasktype);
            } else {
                $this->keyCache[$cacheKey] = sprintf('%sunique:%s:%s', $this->queueKeyPrefix($qname), $tasktype, md5($payload));
            }
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成队列键前缀
     * 
     * @param string $qname 队列名称
     * @return string 键前缀
     */
    protected function queueKeyPrefix(string $qname): string
    {
        $cacheKey = "prefix:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%s:{%s}:', $this->namespace, $qname);
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成任务键前缀
     * 
     * @param string $qname 队列名称
     * @return string 任务键前缀
     */
    protected function taskKeyPrefix(string $qname): string
    {
        $cacheKey = "task_prefix:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%st:', $this->queueKeyPrefix($qname));
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成组键前缀
     * 
     * @param string $qname 队列名称
     * @return string 组键前缀
     */
    protected function groupKeyPrefix(string $qname): string
    {
        $cacheKey = "group_prefix:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%sg:', $this->queueKeyPrefix($qname));
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成任务键
     * 
     * @param string $qname 队列名称
     * @param string $id 任务ID
     * @return string 任务键
     */
    protected function taskKey(string $qname, string $id): string
    {
        $cacheKey = "task:{$qname}:{$id}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%s%s', $this->taskKeyPrefix($qname), $id);
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成待处理队列键
     * 
     * @param string $qname 队列名称
     * @return string 待处理队列键
     */
    protected function pendingKey(string $qname): string
    {
        $cacheKey = "pending:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%spending', $this->queueKeyPrefix($qname));
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成计划任务键
     * 
     * @param string $qname 队列名称
     * @return string 计划任务键
     */
    protected function scheduledKey(string $qname): string
    {
        $cacheKey = "scheduled:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%sscheduled', $this->queueKeyPrefix($qname));
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成组键
     * 
     * @param string $qname 队列名称
     * @param string $gkey 组键
     * @return string 组键
     */
    protected function groupKey(string $qname, string $gkey): string
    {
        $cacheKey = "group:{$qname}:{$gkey}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%s%s', $this->groupKeyPrefix($qname), $gkey);
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成所有组键
     * 
     * @param string $qname 队列名称
     * @return string 所有组键
     */
    protected function allGroups(string $qname): string
    {
        $cacheKey = "all_groups:{$qname}";
        
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%sgroups', $this->queueKeyPrefix($qname));
        }
        
        return $this->keyCache[$cacheKey];
    }

    /**
     * 入队任务
     * 
     * @param TaskMessage $data 任务消息
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function enqueue(TaskMessage $data): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'           => $encoded,
                'state'         => 'pending',
                'pending_since' => $this->nanoseconds(),
            ]);
            
            $this->redis->lPush($this->pendingKey($qname), $taskId);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in enqueue: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 入队唯一任务
     * 
     * @param TaskMessage $data 任务消息
     * @param int $ttl 过期时间（秒）
     * @return bool|int 成功返回 true，任务已存在返回 0，唯一键已存在返回 -1，失败返回 false
     */
    public function enqueueUnique(TaskMessage $data, int $ttl): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            $uniqueKey = $data->getUniqueKey();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 尝试设置唯一键
            if ($this->redis->set($uniqueKey, $taskId, ['NX', 'EX' => $ttl]) !== true) {
                return -1;
            }
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                // 清理已设置的唯一键
                $this->redis->del($uniqueKey);
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'           => $encoded,
                'state'         => 'pending',
                'pending_since' => $this->nanoseconds(),
                'unique_key'    => $uniqueKey,
            ]);
            
            $this->redis->lPush($this->pendingKey($qname), $taskId);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                // 清理已设置的唯一键
                $this->redis->del($uniqueKey);
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in enqueueUnique: ' . $e->getMessage());
            
            // 尝试清理唯一键
            try {
                $this->redis->del($data->getUniqueKey());
            } catch (\RedisException $e2) {
                // 忽略清理失败的异常
            }
            
            return false;
        }
    }

    /**
     * 调度任务
     * 
     * @param TaskMessage $data 任务消息
     * @param int $processAt 处理时间
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function schedule(TaskMessage $data, int $processAt): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'   => $encoded,
                'state' => 'scheduled',
            ]);
            
            $this->redis->zAdd($this->scheduledKey($qname), $processAt, $taskId);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in schedule: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 调度唯一任务
     * 
     * @param TaskMessage $data 任务消息
     * @param int $processAt 处理时间
     * @param int $ttl 过期时间（秒）
     * @return bool|int 成功返回 true，任务已存在返回 0，唯一键已存在返回 -1，失败返回 false
     */
    public function scheduleUnique(TaskMessage $data, int $processAt, int $ttl): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            $uniqueKey = $data->getUniqueKey();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 尝试设置唯一键
            if ($this->redis->set($uniqueKey, $taskId, ['NX', 'EX' => $ttl]) !== true) {
                return -1;
            }
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                // 清理已设置的唯一键
                $this->redis->del($uniqueKey);
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'        => $encoded,
                'state'      => 'scheduled',
                'unique_key' => $uniqueKey,
            ]);
            
            $this->redis->zAdd($this->scheduledKey($qname), $processAt, $taskId);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                // 清理已设置的唯一键
                $this->redis->del($uniqueKey);
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in scheduleUnique: ' . $e->getMessage());
            
            // 尝试清理唯一键
            try {
                $this->redis->del($data->getUniqueKey());
            } catch (\RedisException $e2) {
                // 忽略清理失败的异常
            }
            
            return false;
        }
    }

    /**
     * 添加任务到组
     * 
     * @param TaskMessage $data 任务消息
     * @param string $groupKey 组键
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function addToGroup(TaskMessage $data, string $groupKey): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'   => $encoded,
                'state' => 'aggregating',
                'group' => $groupKey
            ]);
            
            $time = time();
            $this->redis->zAdd($this->groupKey($qname, $groupKey), $time, $taskId);
            $this->redis->sAdd($this->allGroups($qname), $groupKey);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in addToGroup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加唯一任务到组
     * 
     * @param TaskMessage $data 任务消息
     * @param string $groupKey 组键
     * @param int $ttl 过期时间（秒）
     * @return bool|int 成功返回 true，任务已存在返回 0，唯一键已存在返回 -1，失败返回 false
     */
    public function addToGroupUnique(TaskMessage $data, string $groupKey, int $ttl): bool|int
    {
        try {
            $qname = $data->getQueue();
            $taskId = $data->getId();
            
            // 将队列添加到队列列表
            $this->redis->sAdd($this->queuesKey, $qname);
            
            // 生成唯一键
            $unique = $this->uniqueKey($qname, $data->getType(), $data->getPayload());
            
            // 尝试设置唯一键
            if ($this->redis->set($unique, $taskId, ['NX', 'EX' => $ttl]) !== true) {
                return -1;
            }
            
            // 检查任务是否已存在
            $taskKey = $this->taskKey($qname, $taskId);
            if ($this->redis->exists($taskKey) > 0) {
                // 清理已设置的唯一键
                $this->redis->del($unique);
                return 0;
            }
            
            // 执行原子操作
            $this->redis->multi();
            $encoded = $data->serializeToString();
            
            $this->redis->hMSet($taskKey, [
                'msg'   => $encoded,
                'state' => 'aggregating',
                'group' => $groupKey
            ]);
            
            $time = time();
            $this->redis->zAdd($this->groupKey($qname, $groupKey), $time, $taskId);
            $this->redis->sAdd($this->allGroups($qname), $groupKey);
            
            // 执行事务并验证结果
            $results = $this->redis->exec();
            
            // 检查事务是否成功执行
            if ($results === false || in_array(false, $results, true)) {
                // 清理已设置的唯一键
                $this->redis->del($unique);
                return false;
            }
            
            return true;
        } catch (\RedisException $e) {
            // 记录异常信息（实际应用中应使用日志系统）
            error_log('Redis error in addToGroupUnique: ' . $e->getMessage());
            
            // 尝试清理唯一键
            try {
                $unique = $this->uniqueKey($data->getQueue(), $data->getType(), $data->getPayload());
                $this->redis->del($unique);
            } catch (\RedisException $e2) {
                // 忽略清理失败的异常
            }
            
            return false;
        }
    }

    /**
     * 获取 Redis 实例
     * 
     * @return \Redis
     */
    public function getRedis(): \Redis
    {
        return $this->redis;
    }

    /**
     * 设置命名空间
     * 
     * @param string $namespace 命名空间
     * @return $this
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        $this->queuesKey = "{$namespace}:queues";
        // 清空键缓存
        $this->keyCache = [];
        return $this;
    }
}