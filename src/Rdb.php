<?php

namespace Wuwuseo\HibikenAsynqClient;

class Rdb
{
    protected \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    protected string $QueuesKey = "asynq:queues";

    protected function nanoseconds(): string
    {
        [$nanoSeconds, $seconds] = explode(' ', microtime());
        return bcmul((string)$seconds + $nanoSeconds, 1e9);
    }

    public function UniqueKey($qname, $tasktype, $payload){
        if(empty($payload)){
            return sprintf("%sunique:%s:", $this->QueueKeyPrefix($qname), $tasktype);
        }
        return sprintf("%sunique:%s:%s", $this->QueueKeyPrefix($qname), $tasktype, md5($payload));
    }

    protected function QueueKeyPrefix(string $qname): string
    {
        return sprintf("asynq:{%s}:", $qname);
    }

    protected function TaskKeyPrefix(string $qname): string
    {
        return sprintf("%st:", $this->QueueKeyPrefix($qname));
    }

    protected function GroupKeyPrefix(string $qname): string
    {
        return sprintf("%sg:", $this->QueueKeyPrefix($qname));
    }

    protected function TaskKey(string $qname, string $id): string
    {
        return sprintf("%s%s", $this->TaskKeyPrefix($qname), $id);
    }

    protected function PendingKey(string $qname): string
    {
        return sprintf("%spending", $this->QueueKeyPrefix($qname));
    }

    protected function ScheduledKey(string $qname): string
    {
        return sprintf("%sscheduled", $this->QueueKeyPrefix($qname));
    }

    protected function GroupKey(string $qname, string $gkey): string
    {
        return sprintf("%s%s", $this->GroupKeyPrefix($qname), $gkey);
    }

    protected function AllGroups(string $qname): string
    {
        return sprintf("%sgroups", $this->QueueKeyPrefix($qname));
    }

    public function Enqueue(TaskMessage $data)
    {

        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'           => $encoded,
            'state'         => 'pending',
            'pending_since' => $this->nanoseconds(),
        ]);
        $this->redis->lPush($this->PendingKey($data->getQueue()), $data->getId());
        $this->redis->exec();
        return true;
    }

    public function EnqueueUnique(TaskMessage $data, int $ttl)
    {

        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        if ($this->redis->set($data->getUniqueKey(), $data->getId(), ['NX', 'EX' => $ttl]) !== true) {
            return -1;
        }
        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'           => $encoded,
            'state'         => 'pending',
            'pending_since' => $this->nanoseconds(),
            'unique_key'    => $data->getUniqueKey(),
        ]);
        $this->redis->lPush($this->PendingKey($data->getQueue()), $data->getId());
        $this->redis->exec();
        return true;
    }

    public function Schedule(TaskMessage $data, int $processAt)
    {

        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'   => $encoded,
            'state' => 'scheduled',
        ]);
        $this->redis->zAdd($this->ScheduledKey($data->getQueue()), $processAt, $data->getId());
        $this->redis->exec();
        return true;
    }

    public function ScheduleUnique(TaskMessage $data, int $processAt, int $ttl)
    {

        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        if ($this->redis->set($data->getUniqueKey(), $data->getId(), ['NX', 'EX' => $ttl]) !== true) {
            return -1;
        }

        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'        => $encoded,
            'state'      => 'scheduled',
            'unique_key' => $data->getUniqueKey(),
        ]);
        $this->redis->zAdd($this->ScheduledKey($data->getQueue()), $processAt, $data->getId());
        $this->redis->exec();
        return true;
    }

    public function AddToGroup(TaskMessage $data,string $groupKey)
    {

        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'   => $encoded,
            'state' => 'aggregating',
            'group' => $groupKey
        ]);
        $time = time();
        $this->redis->zAdd($this->GroupKey($data->getQueue(),$groupKey), $time, $data->getId());
        $this->redis->sAdd($this->AllGroups($data->getQueue()),$groupKey);
        $this->redis->exec();
        return true;
    }

    public function AddToGroupUnique(TaskMessage $data,string $groupKey,int $ttl)
    {
        $this->redis->sAdd($this->QueuesKey, $data->getQueue());
        $unique = $this->UniqueKey($data->getQueue(),$data->getType(),$data->getPayload());
        if ($this->redis->set($unique, $data->getId(), ['NX', 'EX' => $ttl]) !== true) {
            return -1;
        }
        if ($this->redis->exists($this->TaskKey($data->getQueue(), $data->getId())) > 0) {
            return 0;
        }
        $this->redis->multi();
        $encoded = $data->serializeToString();
        $this->redis->hMSet($this->TaskKey($data->getQueue(), $data->getId()), [
            'msg'   => $encoded,
            'state' => 'aggregating',
            'group' => $groupKey
        ]);
        $time = time();
        $this->redis->zAdd($this->GroupKey($data->getQueue(),$groupKey), $time, $data->getId());
        $this->redis->sAdd($this->AllGroups($data->getQueue()),$groupKey);
        $this->redis->exec();
        return true;
    }
}