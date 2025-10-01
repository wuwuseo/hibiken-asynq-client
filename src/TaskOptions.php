<?php

namespace Wuwuseo\HibikenAsynqClient;

/**
 * TaskOptions 类用于流畅地配置任务选项
 */
class TaskOptions
{
    /**
     * @var array 存储选项的数组
     */
    private array $options = [];

    /**
     * 设置任务的最大重试次数
     * 
     * @param int $maxRetry 最大重试次数
     * @return $this
     */
    public function maxRetry(int $maxRetry): self
    {
        $this->options['retry'] = $maxRetry;
        return $this;
    }

    /**
     * 设置任务的超时时间
     * 
     * @param int $timeout 超时时间（秒）
     * @return $this
     */
    public function timeout(int $timeout): self
    {
        $this->options['timeout'] = $timeout;
        return $this;
    }

    /**
     * 设置任务的截止时间
     * 
     * @param int $deadline 截止时间（Unix 时间戳）
     * @return $this
     */
    public function deadline(int $deadline): self
    {
        $this->options['deadline'] = $deadline;
        return $this;
    }

    /**
     * 设置任务的处理队列
     * 
     * @param string $queue 队列名称
     * @return $this
     */
    public function queue(string $queue): self
    {
        $this->options['queue'] = $queue;
        return $this;
    }

    /**
     * 设置任务的唯一键和过期时间
     * 
     * @param int $ttl 唯一性保持时间（秒）
     * @return $this
     */
    public function unique(int $ttl): self
    {
        $this->options['uniqueTTL'] = $ttl;
        return $this;
    }

    /**
     * 设置任务的处理时间
     * 
     * @param int $timestamp 处理时间（Unix 时间戳）
     * @return $this
     */
    public function processAt(int $timestamp): self
    {
        $this->options['processAt'] = $timestamp;
        return $this;
    }

    /**
     * 设置任务的延迟处理时间
     * 
     * @param int $seconds 延迟秒数
     * @return $this
     */
    public function delay(int $seconds): self
    {
        $this->options['processAt'] = time() + $seconds;
        return $this;
    }

    /**
     * 设置任务的组键
     * 
     * @param string $groupKey 组键
     * @return $this
     */
    public function group(string $groupKey): self
    {
        $this->options['group'] = $groupKey;
        return $this;
    }

    /**
     * 设置任务的保留时间
     * 
     * @param int $retention 保留时间（秒）
     * @return $this
     */
    public function retention(int $retention): self
    {
        $this->options['retention'] = $retention;
        return $this;
    }

    /**
     * 设置自定义任务ID
     * 
     * @param string $taskId 任务ID
     * @return $this
     */
    public function taskId(string $taskId): self
    {
        $this->options['taskID'] = $taskId;
        return $this;
    }

    /**
     * 转换为数组格式
     * 
     * @return array 选项数组
     */
    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * 创建新的 TaskOptions 实例
     * 
     * @return static
     */
    public static function create(): self
    {
        return new static();
    }
}