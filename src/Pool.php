<?php

namespace DeltaGlow\Anemo;

use DeltaGlow\Anemo\Client\HttpClient;
use DeltaGlow\Anemo\Client\Http2Client;
use DeltaGlow\Anemo\Client\WsClient;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class Pool
{
    private array $requests = [];

    public function http(?string $key = null, array $options = []): HttpClient
    {
        $client = new HttpClient($options);
        $client->setPool($this, $key);
        return $client;
    }

    public function http2(?string $key = null, array $options = []): Http2Client
    {
        $client = new Http2Client($options);
        $client->setPool($this, $key);
        return $client;
    }

    public function ws(?string $key = null, array $options = []): WsClient
    {
        $client = new WsClient($options);
        $client->setPool($this, $key);
        return $client;
    }

    public function addRequest(string $key, \Closure $request): void
    {
        $channel = new Channel(1);
        $this->requests[$key] = $channel;
        Coroutine::create(function () use ($channel, $request) {
            try {
                $result = $request();
                $channel->push(['success' => true, 'result' => $result]);
            } catch (\Throwable $ex) {
                $channel->push(['success' => false, 'error' => $ex]);
            }
        });
    }

    public function execute(): array
    {
        $results = [];
        foreach ($this->requests as $key => $channel) {
            $results[$key] = $channel->pop();
        }
        return $results;
    }
}