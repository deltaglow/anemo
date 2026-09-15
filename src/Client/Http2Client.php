<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Response\Response;
use Uri\Rfc3986\Uri;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;

class Http2Client extends BaseHttpClient
{
    protected function doRequest(string $method, Uri $uri, string|array $body = ''): Response
    {
        $secure = $this->isSecure($uri);
        $port = $this->resolvePort($uri, $secure);

        $client = new Client($this->connectHost($uri), $port, $secure);
        $client->set($this->buildSettings());

        if (!$client->connect()) {
            throw new \DeltaGlow\Anemo\Exception\HttpException('HTTP/2 Connection failed: ' . $client->errMsg, $client->errCode);
        }

        $request = new Request();
        $request->method = $method;
        $request->path = $this->buildPath($uri);
        $request->headers = $this->buildHeaders($uri, $port, $secure);
        $request->cookies = $this->cookies;
        $request->data = $this->prepareBody($body);

        $streamId = $client->send($request);
        if ($streamId === false) {
            throw new \DeltaGlow\Anemo\Exception\HttpException('HTTP/2 Request send failed: ' . $client->errMsg, $client->errCode);
        }

        $response = $client->recv();
        if ($response === false) {
            throw new \DeltaGlow\Anemo\Exception\HttpException('HTTP/2 Response receive failed/timeout: ' . $client->errMsg, $client->errCode);
        }

        $this->cookies = $response->cookies;

        return new Response($response);
    }
}