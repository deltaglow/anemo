<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Response\Response;
use GuzzleHttp\Psr7\Uri;
use Swoole\Coroutine\Http\Client;

class HttpClient extends BaseHttpClient
{
    protected function doRequest(string $method, Uri $uri, string|array $body = ''): Response
    {
        $port = $uri->getPort();
        if($port === null) {
            $port = $uri->getScheme() === 'https' ? 443 : 80;
        }

        $client = new Client($uri->getHost(), $port, $uri->getScheme() === 'https');
        $client->set($this->buildSettings());
        $client->setHeaders($this->headers);
        $client->setCookies($this->cookies);
        $client->setData($this->prepareBody($body));
        $client->setMethod($method);
        $client->execute((string)$this->buildPath($uri));

        // Update cookies (simplified, no domain/path/expiry handling)
        $this->cookies = $client->getCookies();

        return new Response($client);
    }
}