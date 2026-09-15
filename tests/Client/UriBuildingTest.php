<?php

namespace DeltaGlow\Anemo\Tests\Client;

use DeltaGlow\Anemo\Client\HttpClient;
use DeltaGlow\Anemo\Client\WsClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Uri\Rfc3986\Uri;

#[RequiresPhpExtension('uri')]
class UriBuildingTest extends TestCase
{
    private function call(object $client, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($client, $method);

        return $reflection->invoke($client, ...$args);
    }

    public static function targetProvider(): array
    {
        return [
            // target                              scheme   host            port  path
            'ip with scheme'      => ['http://192.168.1.10:8080/api', 'http', '192.168.1.10', 8080, '/api'],
            'ip with port'        => ['192.168.1.10:8080/api',        'http', '192.168.1.10', 8080, '/api'],
            'bare ip'             => ['192.168.1.10',                 'http', '192.168.1.10', null, ''],
            'ip and path'         => ['192.168.1.10/api',             'http', '192.168.1.10', null, '/api'],
            'name with port'      => ['example.com:8080/api',         'http', 'example.com',  8080, '/api'],
            'bare name'           => ['example.com/api',              'http', 'example.com',  null, '/api'],
            'scheme relative'     => ['//192.168.1.10/api',           'http', '192.168.1.10', null, '/api'],
            'ipv6'                => ['http://[::1]:8080/api',        'http', '[::1]',        8080, '/api'],
            'ipv6 without scheme' => ['[::1]:8080/api',               'http', '[::1]',        8080, '/api'],
            'https ip'            => ['https://10.0.0.1/api',         'https', '10.0.0.1',    null, '/api'],
        ];
    }

    #[DataProvider('targetProvider')]
    public function testBuildUriAcceptsIpTargets(
        string $target,
        string $scheme,
        string $host,
        ?int $port,
        string $path,
    ): void {
        $uri = $this->call(new HttpClient(), 'buildUri', $target);

        $this->assertSame($scheme, $uri->getScheme());
        $this->assertSame($host, $uri->getHost());
        $this->assertSame($port, $uri->getPort());
        $this->assertSame($path, $uri->getPath());
    }

    public function testBuildUriDefaultsToWsSchemeForWebsockets(): void
    {
        $uri = $this->call(new WsClient(), 'buildUri', '192.168.1.10:8080/socket');

        $this->assertSame('ws', $uri->getScheme());
        $this->assertSame('192.168.1.10', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
    }

    public function testBuildUriAcceptsUriObject(): void
    {
        $uri = $this->call(new HttpClient(), 'buildUri', new Uri('http://192.168.1.10/api'));

        $this->assertSame('192.168.1.10', $uri->getHost());
    }

    public static function baseUriProvider(): array
    {
        return [
            'relative'           => ['users',         'http://192.168.1.10:8080/api/v1/users'],
            'absolute path'      => ['/users',        'http://192.168.1.10:8080/users'],
            'query and fragment' => ['users?a=1#f',   'http://192.168.1.10:8080/api/v1/users?a=1#f'],
            'dot segments'       => ['../admin',      'http://192.168.1.10:8080/api/admin'],
            'absolute overrides' => ['http://other.example/x', 'http://other.example/x'],
        ];
    }

    #[DataProvider('baseUriProvider')]
    public function testBuildUriResolvesAgainstBaseUri(string $target, string $expected): void
    {
        $client = new HttpClient(['base_uri' => 'http://192.168.1.10:8080/api/v1/']);

        $this->assertSame($expected, $this->call($client, 'buildUri', $target)->toString());
    }

    public function testBuildUriRejectsRelativeBaseUri(): void
    {
        $client = new HttpClient(['base_uri' => '/just/a/path']);

        $this->expectException(\InvalidArgumentException::class);
        $this->call($client, 'buildUri', 'users');
    }

    public function testConnectHostStripsIpv6Brackets(): void
    {
        $client = new HttpClient();

        $this->assertSame('::1', $this->call($client, 'connectHost', new Uri('http://[::1]:8080/x')));
        $this->assertSame('192.168.1.10', $this->call($client, 'connectHost', new Uri('http://192.168.1.10/x')));
        $this->assertSame('example.com', $this->call($client, 'connectHost', new Uri('http://example.com/x')));
    }

    public function testConnectHostRejectsHostlessUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->call(new HttpClient(), 'connectHost', new Uri('/no/host'));
    }

    public function testBuildHeadersSetsBracketedHostForIpv6Only(): void
    {
        $client = new HttpClient();

        $this->assertSame(
            ['Host' => '[::1]:8080'],
            $this->call($client, 'buildHeaders', new Uri('http://[::1]:8080/x'), 8080, false),
        );

        // the default port is not appended
        $this->assertSame(
            ['Host' => '[::1]'],
            $this->call($client, 'buildHeaders', new Uri('http://[::1]/x'), 80, false),
        );

        // Swoole already gets this right for anything that is not an IPv6 literal
        $this->assertSame([], $this->call($client, 'buildHeaders', new Uri('http://1.2.3.4:8080/x'), 8080, false));
    }

    public function testBuildHeadersKeepsExplicitHostHeader(): void
    {
        $client = (new HttpClient())->withHeader('host', 'chosen.example');

        $this->assertSame(
            ['host' => 'chosen.example'],
            $this->call($client, 'buildHeaders', new Uri('http://[::1]:8080/x'), 8080, false),
        );
    }

    public function testSchemeAndPortDefaults(): void
    {
        $http = new HttpClient();
        $ws = new WsClient();

        $this->assertTrue($this->call($http, 'isSecure', new Uri('https://1.2.3.4/x')));
        $this->assertFalse($this->call($http, 'isSecure', new Uri('http://1.2.3.4/x')));
        $this->assertTrue($this->call($ws, 'isSecure', new Uri('wss://1.2.3.4/x')));
        $this->assertFalse($this->call($ws, 'isSecure', new Uri('ws://1.2.3.4/x')));

        $this->assertSame(443, $this->call($http, 'resolvePort', new Uri('https://1.2.3.4/x'), true));
        $this->assertSame(80, $this->call($http, 'resolvePort', new Uri('http://1.2.3.4/x'), false));
        $this->assertSame(8080, $this->call($http, 'resolvePort', new Uri('http://1.2.3.4:8080/x'), false));
    }
}
