<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Exception\HttpException;
use DeltaGlow\Anemo\Response\Response;
use Swoole\Coroutine\Http\Client;
use Uri\Rfc3986\Uri;

class HttpClient extends BaseHttpClient
{
    protected function doRequest(string $method, Uri $uri, string|array $body = ''): Response
    {
        $secure = $this->isSecure($uri);
        $port = $this->resolvePort($uri, $secure);

        $client = new Client($this->connectHost($uri), $port, $secure);
        $client->set($this->buildSettings());
        $client->setHeaders($this->buildHeaders($uri, $port, $secure));
        $client->setCookies($this->cookies);
        $client->setData($this->prepareBody($body));
        $client->setMethod($method);

        // execute() expects an origin-form path. Passing the absolute url would put it in the
        // request line, which servers treat as a proxy request and route by its authority.
        $client->execute($this->buildPath($uri));

        if ($client->errCode !== 0) {
            throw new HttpException('Request failed: ' . $client->errMsg, $client->errCode);
        }

        // Update cookies (simplified, no domain/path/expiry handling)
        $this->cookies = $client->getCookies();

        return new Response($client);
    }
}
