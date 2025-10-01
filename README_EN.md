# Hibiken Asynq PHP Client

This is a PHP client for the [hibiken/asynq](https://github.com/hibiken/asynq) Go task queue, designed for sending asynchronous tasks from PHP to Asynq queues. It provides a friendly API, error handling, middleware support, and performance optimizations.

## What is Asynq?

Asynq is a Go library for queueing tasks and processing them asynchronously with workers. It's backed by Redis and designed to be scalable and easy to use.

## Features

- ✅ Clean and intuitive API design
- ✅ Fluent task options configuration (TaskOptions)
- ✅ Complete error handling and return value system
- ✅ Middleware support (extensible task processing flow)
- ✅ Unique task support (prevents duplicate execution)
- ✅ Delayed task scheduling
- ✅ Task grouping functionality
- ✅ Performance optimizations (Redis key caching, batch operations)
- ✅ Support for custom Redis namespaces
- ✅ Complete logging support (PSR-3 compatible)

## System Requirements

```
"php": "^8.1",
"ext-redis": "^5.3 || ^6.0",
"google/protobuf": "^3.24",
"ramsey/uuid": "^4.7"
```

## Installation

Install this package using Composer:

```bash
composer require wuwuseo/hibiken-asynq-client:dev-main
```


```bash
composer require wuwuseo/hibiken-asynq-client:1.1.2
```


## Basic Usage

### 1. Create a Client

```php
use Wuwuseo\HibikenAsynqClient\Client;

// Create Redis connection
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Create basic client
$client = new Client($redis);
```

### 2. Send a Basic Task

```php
$result = $client->enqueue(
    'email:send',  // Task type
    [              // Task payload
        'to' => 'user@example.com',
        'subject' => 'Welcome',
        'body' => 'Thank you for registering!'
    ]
);

// Check result
if ($result === true) {
    echo 'Task enqueued successfully';
} else {
    echo 'Task enqueue failed: ' . $client->getError();
}
```

### 3. Use TaskOptions for Fluent Configuration

TaskOptions provides a fluent API for configuring task options:

```php
use Wuwuseo\HibikenAsynqClient\TaskOptions;

// Configure task options
$options = (new TaskOptions())
    ->maxRetry(3)           // Maximum retry attempts
    ->timeout(60)           // Timeout in seconds
    ->deadline(time() + 300) // Deadline
    ->queue('priority')     // Queue name
    ->build();              // Build options array

// Enqueue task with options
$result = $client->enqueue(
    'payment:process',
    ['order_id' => 'ORD-12345', 'amount' => 99.99],
    $options
);
```

### 4. Delayed Tasks

```php
// Task to be executed after 5 minutes
$result = $client->enqueueIn(
    'reminder:send',
    ['user_id' => 456, 'type' => 'appointment'],
    300 // Delay in seconds
);
```

### 5. Unique Tasks

Prevent duplicate execution of the same task within a specified time frame:

```php
$options = (new TaskOptions())
    ->uniqueTTL(3600) // Ensure uniqueness for 1 hour
    ->build();

$result = $client->enqueue(
    'report:generate',
    ['report_type' => 'daily', 'date' => date('Y-m-d')],
    $options
);

// $result can be:
// true: Task enqueued successfully
// 0: Task already exists
// -1: Unique key already exists
// false: Enqueue failed
```

### 6. Grouped Tasks

Add multiple tasks to the same group:

```php
$groupId = 'batch_export_' . time();
$options = (new TaskOptions())
    ->group($groupId)
    ->build();

// Add multiple tasks to the same group
for ($i = 1; $i <= 5; $i++) {
    $client->enqueue(
        'data:export',
        ['batch_id' => $groupId, 'record_id' => $i],
        $options
    );
}
```

### 7. Using Middleware

Middleware can intercept and modify task data, implementing cross-cutting concerns:

```php
use Psr\Log\LoggerInterface;

// Add logging middleware
$client->addMiddleware(function ($taskData, $next) {
    // Execute before task processing
    $startTime = microtime(true);
    
    // Call the next middleware or final handler
    $result = $next($taskData);
    
    // Execute after task processing
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    echo "Task '{$taskData['type']}' processing time: {$duration} seconds\n";
    
    return $result;
});

// Add authentication middleware example
$client->addMiddleware(function ($taskData, $next) {
    // Check permission to send specific types of tasks
    if (str_starts_with($taskData['type'], 'admin:')) {
        // Authentication logic
        if (!hasAdminPermission()) {
            throw new \Exception('No permission to send admin tasks');
        }
    }
    return $next($taskData);
});
```

### 8. Configure Logging

The client supports PSR-3 compatible loggers:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
$logger = new Logger('asynq-client');
$logger->pushHandler(new StreamHandler('asynq.log', Logger::DEBUG));

// Create client with logger
$client = new Client($redis, $logger);

// The client will now log all operations
```

### 9. Custom Redis Namespace

```php
// Set namespace during construction
$client = new Client($redis, null, 'my_application_namespace');

// Or set later
$client->setNamespace('my_application_namespace');
```

## Performance Optimizations

The optimized client includes several performance improvements:

1. **Redis Key Caching** - Caches frequently used Redis keys to reduce string concatenation operations
2. **Lua Script Support** - Uses Redis Lua scripts to ensure atomicity of task operations
3. **Error Handling** - Comprehensive exception catching and resource cleanup mechanisms
4. **Flexible Configuration** - Support for custom timeouts, retry strategies, etc.

## Error Handling

```php
// Check error message
if (!$result) {
    $error = $client->getError();
    echo "Task processing failed: {$error}\n";
}

// You can also catch exceptions
try {
    $result = $client->enqueue('task:type', ['data' => 'value']);
} catch (\Exception $e) {
    echo "Exception caught: {$e->getMessage()}\n";
}
```

## Return Value Explanation

The `enqueue` and `enqueueIn` methods may return the following values:

- `true`: Task enqueued successfully
- `0`: Task already exists (already in the queue)
- `-1`: Unique key already exists (for unique tasks)
- `false`: Enqueue failed, use `getError()` to get error message

## Complete Examples

### PHP Client Example

Check the `examples/usage_example.php` file for a complete PHP client usage example that demonstrates all features.

### Go Worker Example

The project also includes a Go language worker example for processing tasks sent by the PHP client:

- Location: `examples/worker/main.go`
- Features: Supports processing various task types including email:send, notification:push, reminder:send, etc.
- Characteristics: Includes detailed logging, error handling, and task parsing functionality

## Running Examples

### PHP Client Example

Make sure Redis service is running, then execute:

```bash
php examples/usage_example.php
```

### Go Worker Example

To run the Go worker example, first ensure you have Go environment installed and set up the correct dependencies, then execute:

```bash
# Execute in the project root directory
# If go.mod file is missing, create it
# echo "module github.com/example/asynq-client
go 1.25.1
require github.com/hibiken/asynq v0.25.1" > go.mod
# Install dependencies
# go get github.com/hibiken/asynq@v0.25.1
# Run worker example
go run -mod=mod ./examples/worker/main.go
```

After successful execution, the worker will start listening to Redis queues and process tasks, and the terminal will display output similar to:

```
asynq: pid=54716 2025/10/01 14:00:15.225112 INFO: Starting processing
asynq: pid=54716 2025/10/01 14:00:15.225754 INFO: Send signal TERM or INT to terminate the process
```

## Development and Contribution

Issue submissions and Pull Requests are welcome to improve this project.

## License

This project is licensed under the MIT License.