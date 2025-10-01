<?php

namespace Wuwuseo\HibikenAsynqClient;

use Wuwuseo\HibikenAsynqClient\TaskMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Rdb 类负责与 Redis 交互，提供任务队列操作的底层实现
 */
class Rdb
{
    /**
     * @var \Redis|null Redis 实例，使用前需确保 Redis 扩展已安装
     */
    protected \Redis $redis;
    protected string $queuesKey = "queues";    
    
    /**
     * 获取带命名空间的queuesKey
     * 
     * @return string 带命名空间的queuesKey
     */
    protected function getQueuesKey(): string
    {
        return $this->getNamespacedKey($this->queuesKey);
    }
    protected string $namespace = "";
    
    /**
     * Redis键缓存，用于存储生成的Redis键，减少字符串拼接操作
     * 
     * @var array
     */
    protected array $keyCache = [];
    
    /**
     * 错误信息
     * 
     * @var string
     */
    protected string $error = '';
    
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    
    // Lua脚本缓存，用于原子操作
    private array $luaScripts = [];

    /**
     * Rdb 构造函数
     * 
     * @param \Redis $redis Redis 实例
     * @param string $namespace Redis命名空间
     * @param LoggerInterface|null $logger 日志记录器
     */
    public function __construct(
        \Redis $redis,
        string $namespace = "",
        LoggerInterface $logger = null
    ) {
        $this->redis = $redis;
        $this->namespace = $namespace;
        $this->logger = $logger ?? new NullLogger();
        $this->initLuaScripts();
        $this->initKeyCache();
    }
    
    /**
     * 初始化键缓存
     */
    protected function initKeyCache()
    {
        $this->keyCache = [];
    }
    
    /**
     * 设置Redis命名空间
     * 
     * @param string $namespace 命名空间
     */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
        $this->initKeyCache(); // 重置键缓存
    }
    
    /**
     * 获取带命名空间的键
     * 
     * @param string $key 原始键
     * @return string 带命名空间的键
     */
    protected function getNamespacedKey(string $key): string
    {
        if (empty($this->namespace)) {
            return $key;
        }
        return $this->namespace . ":" . $key;
    }
    
    /**
     * 获取最近的错误信息
     * 
     * @return string 错误信息
     */
    public function getError(): string
    {
        return $this->error;
    }
    
    /**
     * 初始化 Lua 脚本
     */
    private function initLuaScripts(): void
    {
        // 入队操作的 Lua 脚本
        $this->luaScripts['enqueue'] = <<<'LUA'
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[4], ARGV[4])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2],
                       "pending_since", ARGV[3])
            redis.call("LPUSH", KEYS[2], ARGV[4])
            return 1
        LUA;
        
        // 唯一入队操作的 Lua 脚本
        $this->luaScripts['enqueueUnique'] = <<<'LUA'
            if redis.call("EXISTS", KEYS[3]) == 1 then
                return -1
            end
            if redis.call("SET", KEYS[3], ARGV[4], "NX", "EX", ARGV[5]) == false then
                return -1
            end
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[5], ARGV[4])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2],
                       "pending_since", ARGV[3],
                       "unique_key", KEYS[3])
            redis.call("LPUSH", KEYS[2], ARGV[4])
            return 1
        LUA;
        
        // 调度任务的 Lua 脚本
        $this->luaScripts['schedule'] = <<<'LUA'
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[4], ARGV[3])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2])
            redis.call("ZADD", KEYS[3], ARGV[4], ARGV[3])
            return 1
        LUA;
        
        // 调度唯一延迟任务的 Lua 脚本
        $this->luaScripts['scheduleUnique'] = <<<'LUA'
            if redis.call("EXISTS", KEYS[3]) == 1 then
                return -1
            end
            if redis.call("SET", KEYS[3], ARGV[4], "NX", "EX", ARGV[5]) == false then
                return -1
            end
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[4], ARGV[4])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2],
                       "unique_key", KEYS[3])
            redis.call("ZADD", KEYS[3], ARGV[6], ARGV[4])
            return 1
        LUA;
        
        // 添加到分组的 Lua 脚本
        $this->luaScripts['addToGroup'] = <<<'LUA'
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[5], ARGV[3])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2],
                       "group", KEYS[4])
            redis.call("ZADD", KEYS[3], ARGV[5], ARGV[3])
            redis.call("SADD", KEYS[4], KEYS[4])
            return 1
        LUA;
        
        // 添加唯一任务到分组的 Lua 脚本
        $this->luaScripts['addToGroupUnique'] = <<<'LUA'
            local unique_key = ARGV[6]
            if redis.call("EXISTS", unique_key) == 1 then
                return -1
            end
            if redis.call("SET", unique_key, ARGV[3], "NX", "EX", ARGV[5]) == false then
                return -1
            end
            if redis.call("EXISTS", KEYS[1]) == 1 then
                return 0
            end
            redis.call("SADD", KEYS[5], ARGV[3])
            redis.call("HSET", KEYS[1],
                       "msg", ARGV[1],
                       "state", ARGV[2],
                       "group", KEYS[4],
                       "unique_key", unique_key)
            redis.call("ZADD", KEYS[3], ARGV[6], ARGV[3])
            redis.call("SADD", KEYS[4], KEYS[4])
            return 1
        LUA;
    }

    /**
     * 生成纳秒时间戳
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
        if (empty($payload)) {
            return sprintf('%sunique:%s:', $this->queueKeyPrefix($qname), $tasktype);
        }
        return sprintf('%sunique:%s:%s', $this->queueKeyPrefix($qname), $tasktype, md5($payload));
    }

    /**
     * 生成队列键前缀
     * 
     * @param string $qname 队列名称
     * @return string 队列键前缀
     */
    protected function queueKeyPrefix(string $qname): string
    {
        $cacheKey = "queue_prefix:" . $qname;
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = $this->getNamespacedKey(sprintf('{%s}:', $qname));
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
        $cacheKey = "task_prefix:" . $qname;
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
        $cacheKey = "group_prefix:" . $qname;
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
        $cacheKey = "task:" . $qname . ":" . $id;
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%s%s', $this->taskKeyPrefix($qname), $id);
        }
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成待处理任务键
     * 
     * @param string $qname 队列名称
     * @return string 待处理任务键
     */
    protected function pendingKey(string $qname): string
    {
        $cacheKey = "pending:" . $qname;
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%spending', $this->queueKeyPrefix($qname));
        }
        return $this->keyCache[$cacheKey];
    }

    /**
     * 生成调度任务键
     * 
     * @param string $qname 队列名称
     * @return string 调度任务键
     */
    protected function scheduledKey(string $qname): string
    {
        $cacheKey = "scheduled:" . $qname;
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
        $cacheKey = "group:" . $qname . ":" . $gkey;
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
        $cacheKey = "all_groups:" . $qname;
        if (!isset($this->keyCache[$cacheKey])) {
            $this->keyCache[$cacheKey] = sprintf('%sgroups', $this->queueKeyPrefix($qname));
        }
        return $this->keyCache[$cacheKey];
    }

    /**
     * 执行 Redis 事务
     * 
     * @param callable $callback 事务回调函数，接收Redis实例作为参数
     * @return array 事务执行结果
     * @throws Exception 当事务执行失败时
     */
    protected function executeTransaction(callable $callback): array
    {
        try {
            $this->redis->multi();
            call_user_func($callback, $this->redis);
            return $this->redis->exec();
        } catch (Exception $e) {
            // 尝试回滚事务
            try {
                $this->redis->discard();
            } catch (Exception $discardException) {
                // 忽略discard异常
            }
            $errorMessage = "Transaction execution failed: " . $e->getMessage();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }
    
    /**
     * 执行 Lua 脚本
     * 
     * @param string $scriptName 脚本名称
     * @param array $keys 键数组
     * @param array $args 参数数组
     * @return mixed 脚本执行结果
     * @throws Exception 当脚本执行失败时
     */
    protected function executeLuaScript(string $scriptName, array $keys, array $args)
    {
        try {
            // 尝试从Redis缓存中获取脚本SHA
            $scriptSha = $this->redis->script('LOAD', $this->luaScripts[$scriptName]);
            // 正确传递键和参数给evalSha
            return $this->redis->evalSha($scriptSha, array_merge($keys, $args), count($keys));
        } catch (Exception $e) {
            // 尝试直接执行脚本作为备选方案
            try {
                // 确保参数传递正确
                $fullArgs = array_merge($keys, $args);
                return $this->redis->eval($this->luaScripts[$scriptName], $fullArgs, count($keys));
            } catch (Exception $e) {
                $errorMessage = "Lua script execution failed: " . $e->getMessage();
                $this->error = $errorMessage;
                $this->logger->error($errorMessage, ['exception' => $e, 'script_name' => $scriptName]);
                throw new Exception($errorMessage, 0, $e);
            }
        }
    }

    /**
     * 将任务入队
     * 
     * @param TaskMessage $data 任务消息
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function enqueue(TaskMessage $data): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $pendingKey = $this->pendingKey($data->getQueue());
            $encoded = $data->serializeToString();
            $nanos = $this->nanoseconds();
            
            $keys = [$taskKey, $pendingKey, '', $this->getQueuesKey()];
            $args = [$encoded, 'pending', $nanos, $data->getId()];
            
            $result = $this->executeLuaScript('enqueue', $keys, $args);
            
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "Enqueue error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId()
            ]);
            return false;
        }
    }

    /**
     * 将唯一任务入队
     * 
     * @param TaskMessage $data 任务消息
     * @param int $ttl 唯一键的过期时间（秒）
     * @return bool|int 成功返回 true，唯一键冲突返回 -1，任务已存在返回 0，失败返回 false
     */
    public function enqueueUnique(TaskMessage $data, int $ttl): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $pendingKey = $this->pendingKey($data->getQueue());
            $uniqueKey = $data->getUniqueKey();
            $encoded = $data->serializeToString();
            $nanos = $this->nanoseconds();
            
            $keys = [$taskKey, $pendingKey, $uniqueKey, $this->getQueuesKey()];
            $args = [$encoded, 'pending', $nanos, $data->getId(), $ttl];
            
            $result = $this->executeLuaScript('enqueueUnique', $keys, $args);
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "EnqueueUnique error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId(),
                'unique_key' => $uniqueKey
            ]);
            return false;
        }
    }

    /**
     * 调度延迟任务
     * 
     * @param TaskMessage $data 任务消息
     * @param int $processAt 处理时间（Unix 时间戳）
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function schedule(TaskMessage $data, int $processAt): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $scheduledKey = $this->scheduledKey($data->getQueue());
            $encoded = $data->serializeToString();
            
            $keys = [$taskKey, '', $scheduledKey, $this->getQueuesKey()];
            $args = [$encoded, 'scheduled', $data->getId(), $processAt];
            
            $result = $this->executeLuaScript('schedule', $keys, $args);
            
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "Schedule error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId(),
                'process_at' => $processAt
            ]);
            return false;
        }
    }

    /**
     * 调度唯一延迟任务
     * 
     * @param TaskMessage $data 任务消息
     * @param int $processAt 处理时间（Unix 时间戳）
     * @param int $ttl 唯一键的过期时间（秒）
     * @return bool|int 成功返回 true，唯一键冲突返回 -1，任务已存在返回 0，失败返回 false
     */
    public function scheduleUnique(TaskMessage $data, int $processAt, int $ttl): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $uniqueKey = $data->getUniqueKey();
            $encoded = $data->serializeToString();
            
            $keys = [$taskKey, '', $uniqueKey, $this->getQueuesKey()];
            $args = [$encoded, 'scheduled', '', $data->getId(), $ttl, $processAt];
            
            $result = $this->executeLuaScript('scheduleUnique', $keys, $args);
            
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "ScheduleUnique error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId(),
                'unique_key' => $uniqueKey,
                'ttl' => $ttl,
                'process_at' => $processAt
            ]);
            return false;
        }
    }

    /**
     * 添加任务到分组
     * 
     * @param TaskMessage $data 任务消息
     * @param string $groupKey 组键
     * @return bool|int 成功返回 true，任务已存在返回 0，失败返回 false
     */
    public function addToGroup(TaskMessage $data, string $groupKey): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $groupRedisKey = $this->groupKey($data->getQueue(), $groupKey);
            $allGroupsKey = $this->allGroups($data->getQueue());
            $encoded = $data->serializeToString();
            $time = time();
            
            $keys = [$taskKey, '', $groupRedisKey, $allGroupsKey, $this->getQueuesKey()];
            $args = [$encoded, 'aggregating', $data->getId(), $groupKey, $time];
            
            $result = $this->executeLuaScript('addToGroup', $keys, $args);
            
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "AddToGroup error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId(),
                'group_key' => $groupKey
            ]);
            return false;
        }
    }

    /**
     * 添加唯一任务到分组
     * 
     * @param TaskMessage $data 任务消息
     * @param string $groupKey 组键
     * @param int $ttl 唯一键的过期时间（秒）
     * @return bool|int 成功返回 true，唯一键冲突返回 -1，任务已存在返回 0，失败返回 false
     */
    public function addToGroupUnique(TaskMessage $data, string $groupKey, int $ttl): bool|int
    {
        try {
            $taskKey = $this->taskKey($data->getQueue(), $data->getId());
            $groupRedisKey = $this->groupKey($data->getQueue(), $groupKey);
            $allGroupsKey = $this->allGroups($data->getQueue());
            $uniqueKey = $this->uniqueKey($data->getQueue(), $data->getType(), $data->getPayload());
            $encoded = $data->serializeToString();
            $time = time();
            
            $keys = [$taskKey, '', $groupRedisKey, $allGroupsKey, $this->getQueuesKey()];
            $args = [$encoded, 'aggregating', $data->getId(), $groupKey, $ttl, $uniqueKey, $time];
            
            $result = $this->executeLuaScript('addToGroupUnique', $keys, $args);
            
            return $result === 1 ? true : $result;
        } catch (Exception $e) {
            // 记录错误日志
            $errorMessage = "AddToGroupUnique error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            $this->error = $errorMessage;
            $this->logger->error($errorMessage, [
                'exception' => $e,
                'queue' => $data->getQueue(),
                'task_id' => $data->getId(),
                'group_key' => $groupKey,
                'unique_key' => $uniqueKey,
                'ttl' => $ttl
            ]);
            return false;
        }
    }
}