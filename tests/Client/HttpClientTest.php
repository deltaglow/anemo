<?php

namespace DeltaGlow\Anemo\Tests\Client;

use DeltaGlow\Anemo\Client\HttpClient;
use Uri\Rfc3986\Uri;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\RequiresPhpExtension('uri')]
class HttpClientTest extends TestCase
{
    public function testBuildSettingsProxy(): void
    {
        $client = new HttpClient([
            'proxy' => ['uri' => 'http://user:pass@127.0.0.1:8080'],
        ]);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('buildSettings');
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

        $this->assertEquals(['X-Test' => '1'], $property->getValue($client));

        $client->withHeaders(['X-Test-2' => '2']);
        $this->assertEquals(['X-Test' => '1', 'X-Test-2' => '2'], $property->getValue($client));
    }

    public function testBuildPath(): void
    {
        $client = new HttpClient();
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('buildPath');

        $this->assertEquals('/foo?bar=baz', $method->invoke($client, new Uri('https://example.com/foo?bar=baz')));

        // an empty path is still a valid request target
        $this->assertEquals('/', $method->invoke($client, new Uri('https://example.com')));

        // the fragment is a client side concern and must never reach the request line
        $this->assertEquals('/foo?bar=baz', $method->invoke($client, new Uri('https://example.com/foo?bar=baz#frag')));
    }
}
