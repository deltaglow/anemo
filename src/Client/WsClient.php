<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Exception\WsException;
use DeltaGlow\Anemo\Response\WsConnection;
use Uri\Rfc3986\Uri;

class WsClient extends BaseClient {
    protected const DEFAULT_SCHEME = 'ws';
    protected const SECURE_SCHEMES = ['wss', 'https'];

    public function upgrade(string|Uri $url): ?WsConnection
    {
        $uri = $this->buildUri($url);

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
        $secure = $this->isSecure($uri);
        $port = $this->resolvePort($uri, $secure);

        $client = new WsConnection($this->connectHost($uri), $port, $secure);
        $client->set($this->buildSettings());
        $client->setHeaders($this->buildHeaders($uri, $port, $secure));
        $client->setCookies($this->cookies);

        if($this->options['ws']['autoping']) {
            $client->startAutoping($this->options['ws']['autoping_interval'], $this->options['ws']['autoping_data']);
        }

        $upgraded = $client->upgrade($this->buildPath($uri));
        if (!$upgraded) {
            throw new WsException('WebSocket upgrade failed: ' . $client->errMsg);
        }

        $this->cookies = $client->getCookies();
        return $client;
    }
}
