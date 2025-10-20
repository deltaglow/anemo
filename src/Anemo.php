<?php

namespace DeltaGlow\Anemo;

use DeltaGlow\Anemo\Client\HttpClient;
use DeltaGlow\Anemo\Client\Http2Client;
use DeltaGlow\Anemo\Client\WsClient;

class Anemo
{
    public static function http(array $options = []): HttpClient
    {
        return new HttpClient($options);
    }

    public static function http2(array $options = []): Http2Client
    {
        return new Http2Client($options);
    }

    public static function ws(array $options = []): WsClient
    {
        return new WsClient($options);
    }

    public static function pool(callable $definition): array
    {
        $pool = new Pool();
        $definition($pool);
        return $pool->execute();
    }
}