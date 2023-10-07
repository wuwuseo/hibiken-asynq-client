# hibiken-asynq-client

## This is a client for the hibiken/asynq GO task queue used to send asynq tasks in PHP.

## Asynq issues Client in other languages
https://github.com/hibiken/asynq/issues/105#issue-577400538
https://github.com/hibiken/asynq/issues/105#issuecomment-875502989

## Asynq 
https://github.com/hibiken/asynq 
  Asynq is a Go library for queueing tasks and processing them asynchronously with workers. It's backed by Redis and is designed to be scalable yet easy to get started.

## required
```
"php": "^8.1",
"ext-redis": "^5.3",
```

## install

composer require wuwuseo/hibiken-asynq-client:dev-main

## used

 use Client to put tasks on queues.

 example

```php
namespace Wuwuseo\HibikenAsynqClient\Tests;

use Ramsey\Uuid\Uuid;
use Wuwuseo\HibikenAsynqClient\Client;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testEnqueue()
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        $clinet = new Client($redis);
        $res = $clinet->Enqueue([
            'typename'=>'newtest:user:xxxx',
            'payload'=>[
                'test'=>'xxxx',
                'user'=>1111
            ],
            'opts'=>[
                'timeout'=>0,
            ]
        ],[
            'queue'=>'test'        
        ]);
        $this->assertTrue($res);
    }
}
```
