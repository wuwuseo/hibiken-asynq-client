<?php
namespace Wuwuseo\HibikenAsynqClient;

use Ramsey\Uuid\Uuid;

Class Client
{
    /**
     * @var Rdb
     */
    protected $broker;

    protected string $error = '';

    protected int $defaultTimeout = 3600;

    protected int $defaultMaxRetry = 25;

    public function __construct(\Redis $redis)
    {
        $this->broker = new Rdb($redis);
    }

    public function getMessage(){
        return $this->error;
    }

    protected function composeOptions($options = []){
        $option = [
            'retry'=>$this->defaultMaxRetry,
            'queue'=>'default',
            'taskID'=>(string)uuid::uuid4(),
            'timeout'=>0,
            'deadline'=>0,
            'processAt'=>time(),
        ];
        if (isset($options['queue'])){
            if(empty(trim($options['queue']))){
                unset($options['queue']);
            } else {
                $options['queue'] = trim($options['queue']);
            }

        }
        if (isset($options['taskID'])){
            if(empty($options['taskID'])){
                unset($options['taskID']);
            }
        }
        if (isset($options['uniqueTTL'])){
            if(empty($options['uniqueTTL']) || $options['uniqueTTL'] < 1){
                unset($options['uniqueTTL']);
            }
        }

        if (isset($options['group'])){
            if(empty($options['group'])){
                unset($options['group']);
            }
        }

        return array_merge($option,$options);
    }

    /**
     * @param array $task
     *
     *  @type string typename 任务标识
     *  @type array payload 任务载荷
     *  @type array opts 任务选项
     *
     * @param $opts
     *
     * @return int|bool
     */
    public function Enqueue($task = [], $opts = []): int|bool
    {
        if(!isset($task['typename']) || !isset($task['payload']) || empty(trim($task['typename'])) || empty($task['payload'])){
            throw new \Exception('Task parameters are missing');
        }
        if(isset($task['opts']) && !empty($opts)){
            $opts = array_merge($task['opts'],$opts);
        }
        $opts = $this->composeOptions($opts);
        $payload = json_encode($task['payload']);
        $taskType = trim($task['typename']);
        if($payload === false){
            $payload = '';
        }
        $timeout = 0;
        if($opts['timeout'] > 0){
            $timeout = $opts['timeout'];
        }

        if($opts['deadline'] === 0 && $timeout === 0){
            $timeout = $this->defaultTimeout;
        }
        $ttl = 0;
        if(isset($opts['uniqueTTL']) && $opts['uniqueTTL'] > 0){
            $uniqueKey = $this->broker->UniqueKey($opts['queue'], $taskType, $task['typename'],$payload);
            $ttl = $opts['uniqueTTL'];
        }
        $msg = new TaskMessage();
        $msg->setId($opts['taskID']);
        $msg->setType($taskType);
        $msg->setPayload($payload);
        $msg->setQueue($opts['queue']);
        $msg->setRetry($opts['retry']);
        $msg->setDeadline($opts['deadline']);
        $msg->setTimeout($timeout);
        if(isset($uniqueKey)){
            $msg->setUniqueKey($uniqueKey);
        }
        if(isset($opts['group'])){
            $msg->setGroupKey($opts['group']);
        }
        if(isset($opts['retention'])){
            $msg->setRetention($opts['retention']);
        }
        $now = time();

        if($opts['processAt'] > $now){

            return $this->schedule($msg,$opts['processAt'],$ttl);
        } else if(isset($opts['group']) && !empty($opts['group'])){
            return $this->addToGroup($msg,$opts['group'],$ttl);
        } else {
            return $this->enqueueDo($msg,$ttl);
        }
    }

    protected function schedule($msg, $processAt, $uniqueTTL = 0): bool|int
    {
        if($uniqueTTL > 0){
            return $this->broker->ScheduleUnique($msg,$processAt, $uniqueTTL);
        }
        return $this->broker->Schedule($msg,$processAt);
    }

    protected function enqueueDo($msg, $uniqueTTL = 0): bool|int
    {
        if($uniqueTTL > 0){
            return $this->broker->EnqueueUnique($msg,$uniqueTTL);
        }
        return $this->broker->Enqueue($msg);
    }

    protected function addToGroup($msg,$group,$uniqueTTL = 0){
        if($uniqueTTL > 0){
            return $this->broker->AddToGroupUnique($msg,$group,$uniqueTTL);
        }
        return $this->broker->AddToGroup($msg,$group);
    }

}
