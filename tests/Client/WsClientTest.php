<?php

namespace DeltaGlow\Anemo\Tests\Client;

use DeltaGlow\Anemo\Client\WsClient;
use PHPUnit\Framework\TestCase;

class WsClientTest extends TestCase
{
    public function testConstructorOptions(): void
    {
        $client = new WsClient([
            'timeout' => 10,
            'ssl_verify_peer' => false
        ]);

        $reflection = new \ReflectionClass($client);

        $timeout = $reflection->getProperty('timeout');
        $timeout->setAccessible(true);
        $this->assertEquals(10, $timeout->getValue($client));

        $ssl = $reflection->getProperty('ssl_verify_peer');
        $ssl->setAccessible(true);
        $this->assertFalse($ssl->getValue($client));
    }
}
