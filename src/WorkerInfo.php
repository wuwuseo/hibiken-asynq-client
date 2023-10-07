<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: src/proto/asynq.proto

namespace Wuwuseo\HibikenAsynqClient;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * WorkerInfo holds information about a running worker.
 *
 * Generated from protobuf message <code>asynq.WorkerInfo</code>
 */
class WorkerInfo extends \Google\Protobuf\Internal\Message
{
    /**
     * Host matchine this worker is running on.
     *
     * Generated from protobuf field <code>string host = 1;</code>
     */
    protected $host = '';
    /**
     * PID of the process in which this worker is running.
     *
     * Generated from protobuf field <code>int32 pid = 2;</code>
     */
    protected $pid = 0;
    /**
     * ID of the server in which this worker is running.
     *
     * Generated from protobuf field <code>string server_id = 3;</code>
     */
    protected $server_id = '';
    /**
     * ID of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_id = 4;</code>
     */
    protected $task_id = '';
    /**
     * Type of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_type = 5;</code>
     */
    protected $task_type = '';
    /**
     * Payload of the task this worker is processing.
     *
     * Generated from protobuf field <code>bytes task_payload = 6;</code>
     */
    protected $task_payload = '';
    /**
     * Name of the queue the task the worker is processing belongs.
     *
     * Generated from protobuf field <code>string queue = 7;</code>
     */
    protected $queue = '';
    /**
     * Time this worker started processing the task.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp start_time = 8;</code>
     */
    protected $start_time = null;
    /**
     * Deadline by which the worker needs to complete processing 
     * the task. If worker exceeds the deadline, the task will fail.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp deadline = 9;</code>
     */
    protected $deadline = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $host
     *           Host matchine this worker is running on.
     *     @type int $pid
     *           PID of the process in which this worker is running.
     *     @type string $server_id
     *           ID of the server in which this worker is running.
     *     @type string $task_id
     *           ID of the task this worker is processing.
     *     @type string $task_type
     *           Type of the task this worker is processing.
     *     @type string $task_payload
     *           Payload of the task this worker is processing.
     *     @type string $queue
     *           Name of the queue the task the worker is processing belongs.
     *     @type \Google\Protobuf\Timestamp $start_time
     *           Time this worker started processing the task.
     *     @type \Google\Protobuf\Timestamp $deadline
     *           Deadline by which the worker needs to complete processing 
     *           the task. If worker exceeds the deadline, the task will fail.
     * }
     */
    public function __construct($data = NULL) {
        \Wuwuseo\HibikenAsynqClient\metadata\Asynq::initOnce();
        parent::__construct($data);
    }

    /**
     * Host matchine this worker is running on.
     *
     * Generated from protobuf field <code>string host = 1;</code>
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Host matchine this worker is running on.
     *
     * Generated from protobuf field <code>string host = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setHost($var)
    {
        GPBUtil::checkString($var, True);
        $this->host = $var;

        return $this;
    }

    /**
     * PID of the process in which this worker is running.
     *
     * Generated from protobuf field <code>int32 pid = 2;</code>
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * PID of the process in which this worker is running.
     *
     * Generated from protobuf field <code>int32 pid = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setPid($var)
    {
        GPBUtil::checkInt32($var);
        $this->pid = $var;

        return $this;
    }

    /**
     * ID of the server in which this worker is running.
     *
     * Generated from protobuf field <code>string server_id = 3;</code>
     * @return string
     */
    public function getServerId()
    {
        return $this->server_id;
    }

    /**
     * ID of the server in which this worker is running.
     *
     * Generated from protobuf field <code>string server_id = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setServerId($var)
    {
        GPBUtil::checkString($var, True);
        $this->server_id = $var;

        return $this;
    }

    /**
     * ID of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_id = 4;</code>
     * @return string
     */
    public function getTaskId()
    {
        return $this->task_id;
    }

    /**
     * ID of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_id = 4;</code>
     * @param string $var
     * @return $this
     */
    public function setTaskId($var)
    {
        GPBUtil::checkString($var, True);
        $this->task_id = $var;

        return $this;
    }

    /**
     * Type of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_type = 5;</code>
     * @return string
     */
    public function getTaskType()
    {
        return $this->task_type;
    }

    /**
     * Type of the task this worker is processing.
     *
     * Generated from protobuf field <code>string task_type = 5;</code>
     * @param string $var
     * @return $this
     */
    public function setTaskType($var)
    {
        GPBUtil::checkString($var, True);
        $this->task_type = $var;

        return $this;
    }

    /**
     * Payload of the task this worker is processing.
     *
     * Generated from protobuf field <code>bytes task_payload = 6;</code>
     * @return string
     */
    public function getTaskPayload()
    {
        return $this->task_payload;
    }

    /**
     * Payload of the task this worker is processing.
     *
     * Generated from protobuf field <code>bytes task_payload = 6;</code>
     * @param string $var
     * @return $this
     */
    public function setTaskPayload($var)
    {
        GPBUtil::checkString($var, False);
        $this->task_payload = $var;

        return $this;
    }

    /**
     * Name of the queue the task the worker is processing belongs.
     *
     * Generated from protobuf field <code>string queue = 7;</code>
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Name of the queue the task the worker is processing belongs.
     *
     * Generated from protobuf field <code>string queue = 7;</code>
     * @param string $var
     * @return $this
     */
    public function setQueue($var)
    {
        GPBUtil::checkString($var, True);
        $this->queue = $var;

        return $this;
    }

    /**
     * Time this worker started processing the task.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp start_time = 8;</code>
     * @return \Google\Protobuf\Timestamp|null
     */
    public function getStartTime()
    {
        return $this->start_time;
    }

    public function hasStartTime()
    {
        return isset($this->start_time);
    }

    public function clearStartTime()
    {
        unset($this->start_time);
    }

    /**
     * Time this worker started processing the task.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp start_time = 8;</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setStartTime($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->start_time = $var;

        return $this;
    }

    /**
     * Deadline by which the worker needs to complete processing 
     * the task. If worker exceeds the deadline, the task will fail.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp deadline = 9;</code>
     * @return \Google\Protobuf\Timestamp|null
     */
    public function getDeadline()
    {
        return $this->deadline;
    }

    public function hasDeadline()
    {
        return isset($this->deadline);
    }

    public function clearDeadline()
    {
        unset($this->deadline);
    }

    /**
     * Deadline by which the worker needs to complete processing 
     * the task. If worker exceeds the deadline, the task will fail.
     *
     * Generated from protobuf field <code>.google.protobuf.Timestamp deadline = 9;</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setDeadline($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->deadline = $var;

        return $this;
    }

}
