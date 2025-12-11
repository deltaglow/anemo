<?php

namespace DeltaGlow\Anemo\Tests\Client;

use DeltaGlow\Anemo\Client\HttpClient;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    public function testBuildSettingsProxy(): void
    {
        $client = new HttpClient([
            'proxy_uri' => 'http://user:pass@127.0.0.1:8080'
        ]);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('buildSettings');
        $method->setAccessible(true);
        $settings = $method->invoke($client);

        $this->assertEquals('127.0.0.1', $settings['http_proxy_host']);
        $this->assertEquals(8080, $settings['http_proxy_port']);
        $this->assertEquals('user', $settings['http_proxy_user']);
        $this->assertEquals('pass', $settings['http_proxy_password']);
    }

    public function testWithCookies(): void
    {
        $client = new HttpClient();
        $client->withCookie('foo', 'bar');

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('cookies');
        $property->setAccessible(true);

        $this->assertEquals(['foo' => 'bar'], $property->getValue($client));

        $client->withCookies(['baz' => 'qux']);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $property->getValue($client));
    }

    public function testWithHeaders(): void
    {
        $client = new HttpClient();
        $client->withHeader('X-Test', '1');

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $this->assertEquals(['X-Test' => '1'], $property->getValue($client));

        $client->withHeaders(['X-Test-2' => '2']);
        $this->assertEquals(['X-Test' => '1', 'X-Test-2' => '2'], $property->getValue($client));
    }

    public function testBuildPath(): void
    {
        $client = new HttpClient();
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('buildPath');
        $method->setAccessible(true);

        $uri = new Uri('https://example.com/foo?bar=baz');
        $path = $method->invoke($client, $uri);

        $this->assertEquals('/foo?bar=baz', (string) $path);
    }
}
