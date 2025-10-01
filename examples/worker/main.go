package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"

	"github.com/hibiken/asynq"
)

type EmailTaskPayload struct {
	UserID int
}

// 封装一个通用的 JSON 反序列化函数，减少代码重复
func unmarshalPayload(t *asynq.Task, v interface{}) error {
	if err := json.Unmarshal(t.Payload(), v); err != nil {
		log.Printf("[ERROR] 解析 %s 任务有效载荷失败: %v", t.Type(), err)
		return err
	}
	return nil
}

func handler(ctx context.Context, t *asynq.Task) error {
	// 记录详细的任务信息
	log.Printf("[DEBUG] 开始处理任务: 类型=%s, 有效载荷=%s",
		t.Type(), t.Payload())

	// 打印原始任务信息
	fmt.Printf(" [*] Received task: %s, payload: %s\n", t.Type(), t.Payload())

	switch t.Type() {
	case "email:send":
		log.Printf("[INFO] 处理 email:send 类型的任务")
		var p map[string]interface{}
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Send Email to %s with subject: %s", p["to"], p["subject"])
		log.Printf("[DEBUG] 完成 email:send 任务的处理")

	case "notification:push":
		log.Printf("[INFO] 处理 notification:push 类型的任务")
		var p map[string]interface{}
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Send Notification to user %d: %s", p["user_id"], p["message"])
		log.Printf("[DEBUG] 完成 notification:push 任务的处理")

	case "reminder:send":
		log.Printf("[INFO] 处理 reminder:send 类型的任务")
		var p map[string]interface{}
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Send Reminder to user %d about %s at %s", p["user_id"], p["type"], p["time"])
		log.Printf("[DEBUG] 完成 reminder:send 任务的处理")

	case "analytics:track":
		log.Printf("[INFO] 处理 analytics:track 类型的任务")
		var p map[string]interface{}
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Track Analytics event: %s for user %d on page %s", p["event"], p["user_id"], p["page"])
		log.Printf("[DEBUG] 完成 analytics:track 任务的处理")

	case "email:welcome":
		log.Printf("[INFO] 处理 email:welcome 类型的任务")
		var p EmailTaskPayload
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Send Welcome Email to User %d", p.UserID)
		log.Printf("[DEBUG] 完成 email:welcome 任务的处理")

	case "email:reminder":
		log.Printf("[INFO] 处理 email:reminder 类型的任务")
		var p EmailTaskPayload
		if err := unmarshalPayload(t, &p); err != nil {
			return err
		}
		log.Printf(" [*] Send Reminder Email to User %d", p.UserID)
		log.Printf("[DEBUG] 完成 email:reminder 任务的处理")

	default:
		log.Printf("[WARNING] 处理未知类型的任务: %s", t.Type())
		fmt.Printf(" [*] WARNING: Unknown task type: %s\n", t.Type())
		// 即使在 default 情况下也打印任务详情，以便更好地调试
		log.Printf("[DEBUG] 未知任务的详细信息: 有效载荷=%s",
			t.Payload())
		// 为了演示目的，我们不返回错误，而是继续处理
		// return fmt.Errorf("unexpected task type: %s", t.Type())
	}
	log.Printf("[DEBUG] 成功完成任务处理: 类型=%s", t.Type())
	return nil
}

func main() {
	srv := asynq.NewServer(
		asynq.RedisClientOpt{Addr: "localhost:6379"},
		asynq.Config{Concurrency: 10},
	)

	// Use asynq.HandlerFunc adapter for a handler function
	if err := srv.Run(asynq.HandlerFunc(handler)); err != nil {
		log.Fatal(err)
	}
}
