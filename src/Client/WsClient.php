<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Exception\WsException;
use DeltaGlow\Anemo\Response\WsConnection;
use GuzzleHttp\Psr7\Uri;

class WsClient extends BaseClient {
    public function upgrade(string $url): ?WsConnection
    {
        $uri = new Uri($url);
        if ($this->pool) {
            $this->pool->addRequest($this->pool_key, function () use ($uri) {
                return $this->doUpgrade($uri);
            });
            return null;
        } else {
            return $this->doUpgrade($uri);
        }
    }

    protected function doUpgrade(Uri $uri): WsConnection
    {
        $port = $uri->getPort();
        if($port === null) {
            $port = $uri->getScheme() === 'wss' ? 443 : 80;
        }

        $client = new WsConnection($uri->getHost(), $port, $uri->getScheme() === 'wss');
        $client->set($this->buildSettings());
        $client->setHeaders($this->headers);
        $client->setCookies($this->cookies);

        $upgraded = $client->upgrade((string)$this->buildPath($uri));
        if (!$upgraded) {
            throw new WsException('WebSocket upgrade failed: ' . $client->errMsg);
        }

        $this->cookies = $client->getCookies();
        return $client;
    }
}