<?php
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
        $res = $clinet->enqueue('newtest:user:xxxx',[
                'test'=>'test',
                'user'=>1111
            
        ]);
        $this->assertTrue($res);
    }
}