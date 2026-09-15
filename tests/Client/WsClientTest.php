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
            'ssl' => ['verify_peer' => false],
        ]);

        $reflection = new \ReflectionClass($client);
        $options = $reflection->getProperty('options');
        $resolved = $options->getValue($client);

        $this->assertEquals(10, $resolved['timeout']);
        $this->assertFalse($resolved['ssl']['verify_peer']);
    }
}
