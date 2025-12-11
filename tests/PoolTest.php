<?php

namespace DeltaGlow\Anemo\Tests;

use DeltaGlow\Anemo\Client\HttpClient;
use DeltaGlow\Anemo\Client\Http2Client;
use DeltaGlow\Anemo\Client\WsClient;
use DeltaGlow\Anemo\Pool;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testPoolClientFactories(): void
    {
        $pool = new Pool();

        $http = $pool->http('http_key', ['timeout' => 1]);
        $this->assertInstanceOf(HttpClient::class, $http);

        $http2 = $pool->http2('http2_key', ['timeout' => 1]);
        $this->assertInstanceOf(Http2Client::class, $http2);

        $ws = $pool->ws('ws_key', ['timeout' => 1]);
        $this->assertInstanceOf(WsClient::class, $ws);
    }

    public function testAddRequestAndExecute(): void
    {
        \Swoole\Coroutine\run(function () {
            $pool = new Pool();

            $pool->addRequest('req1', function () {
                return 'val1';
            });

            $pool->addRequest('req2', function () {
                return 'val2';
            });

            $results = $pool->execute();

            $this->assertCount(2, $results);
            $this->assertEquals(['success' => true, 'result' => 'val1'], $results['req1']);
            $this->assertEquals(['success' => true, 'result' => 'val2'], $results['req2']);
        });
    }

    public function testAddRequestExceptionHandling(): void
    {
        \Swoole\Coroutine\run(function () {
            $pool = new Pool();

            $pool->addRequest('fail', function () {
                throw new \RuntimeException('error');
            });

            $results = $pool->execute();

            $this->assertFalse($results['fail']['success']);
            $this->assertInstanceOf(\RuntimeException::class, $results['fail']['error']);
            $this->assertEquals('error', $results['fail']['error']->getMessage());
        });
    }
}
