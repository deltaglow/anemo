<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Response\Response;
use Swoole\Coroutine\Http\Client;
use Uri\Rfc3986\Uri;

class HttpClient extends BaseHttpClient
{
    protected function doRequest(string $method, Uri $uri, string|array $body = ''): Response
    {
        $port = $uri->getPort();
        if ($port === null) {
            $port = $uri->getScheme() === 'https' ? 443 : 80;
        }

        $client = new Client($uri->getHost(), $port, $uri->getScheme() === 'https');
        $client->set($this->buildSettings());
        $client->setHeaders($this->headers);
        $client->setCookies($this->cookies);
        $client->setData($this->prepareBody($body));
        $client->setMethod($method);
        $client->execute($uri->toString());

        if ($client->errCode !== 0) {
            throw new \DeltaGlow\Anemo\Exception\HttpException('Request failed: ' . $client->errMsg, $client->errCode);
        }

        // Update cookies (simplified, no domain/path/expiry handling)
        $this->cookies = $client->getCookies();

        return new Response($client);
    }
}