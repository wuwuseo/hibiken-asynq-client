<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: src/proto/asynq.proto

namespace Wuwuseo\HibikenAsynqClient\metadata;

class Asynq
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Google\Protobuf\Timestamp::initOnce();
        $pool->internalAddGeneratedFile(
            '
�	
src/proto/asynq.protoasynq"�
TaskMessage
type (	
payload (

id (	
queue (	
retry (
retried (
	error_msg (	
last_failed_at (
timeout (
deadline	 (

unique_key
 (	
	group_key (	
	retention (
completed_at

ServerInfo
host (	
pid (
	server_id (	
concurrency (-
queues (2.asynq.ServerInfo.QueuesEntry
strict_priority (
status (	.

start_time (2.google.protobuf.Timestamp
active_worker_count	 (-
QueuesEntry
key (	
value (:8"�

WorkerInfo
host (	
pid (
	server_id (	
task_id (	
	task_type (	
task_payload (
queue (	.

start_time (2.google.protobuf.Timestamp,
deadline	 (2.google.protobuf.Timestamp"�
SchedulerEntry

id (	
spec (	
	task_type (	
task_payload (
enqueue_options (	5
next_enqueue_time (2.google.protobuf.Timestamp5
prev_enqueue_time (2.google.protobuf.Timestamp"Z
SchedulerEnqueueEvent
task_id (	0
enqueue_time (2.google.protobuf.TimestampBlZ\'github.com/hibiken/asynq/internal/proto�Wuwuseo\\HibikenAsynqClient�#Wuwuseo\\HibikenAsynqClient\\metadatabproto3'
        , true);

        static::$is_initialized = true;
    }
}
