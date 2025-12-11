<?php

namespace DeltaGlow\Anemo\Tests;

use DeltaGlow\Anemo\Anemo;
use DeltaGlow\Anemo\Client\HttpClient;
use DeltaGlow\Anemo\Client\Http2Client;
use DeltaGlow\Anemo\Client\WsClient;
use DeltaGlow\Anemo\Pool;
use PHPUnit\Framework\TestCase;

class AnemoTest extends TestCase
{
    public function testHttpFactory(): void
    {
        $client = Anemo::http(['timeout' => 5]);
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function testHttp2Factory(): void
    {
        $client = Anemo::http2(['timeout' => 5]);
        $this->assertInstanceOf(Http2Client::class, $client);
    }

    public function testWsFactory(): void
    {
        $client = Anemo::ws(['timeout' => 5]);
        $this->assertInstanceOf(WsClient::class, $client);
    }

    public function testPoolFactory(): void
    {
        \Swoole\Coroutine\run(function () {
            $results = Anemo::pool(function (Pool $pool) {
                $pool->addRequest('key', function () {
                    return 'result';
                });
            });

            $this->assertIsArray($results);
            $this->assertArrayHasKey('key', $results);
            $this->assertEquals(['success' => true, 'result' => 'result'], $results['key']);
        });
    }
}
