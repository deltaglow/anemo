<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Response\Response;
use GuzzleHttp\Psr7\Uri;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;

class Http2Client extends BaseHttpClient
{
    protected function doRequest(string $method, Uri $uri, $body): Response
    {
        $port = $uri->getPort();
        if($port === null) {
            $port = $uri->getScheme() === 'https' ? 443 : 80;
        }

        $client = new Client($uri->getHost(), $port, $uri->getScheme() === 'https');
        $client->set($this->buildSettings());

        $client->connect();

        $request = new Request();
        $request->method = $method;
        $request->path = $this->buildPath($uri);
        $request->headers = $this->headers;
        $request->cookies = $this->cookies;
        $request->data = $this->prepareBody($body);

        $client->send($request);
        $response = $client->recv();

        $this->cookies = $response->cookies;

        return new Response($response);
    }
}