<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Response\Response;
use GuzzleHttp\Psr7\Uri;

abstract class BaseHttpClient extends BaseClient
{
    protected string $body_format = 'json';

    abstract protected function doRequest(string $method, Uri $uri, $body): Response;

    public function request(string $method, string $url, array $body = []): mixed
    {
        $uri = $this->buildUri($url);

        if ($this->pool) {
            $this->pool->addRequest($this->pool_key, function () use ($method, $uri, $body) {
                return $this->doRequest($method, $uri, $body);
            });
            return null;
        } else {
            return $this->doRequest($method, $uri, $body);
        }
    }

    public function get(string $url): mixed
    {
        return $this->request('GET', $url);
    }

    public function post(string $url, array $body = []): mixed
    {
        return $this->request('POST', $url, $body);
    }

    public function put(string $url, array $body = []): mixed
    {
        return $this->request('PUT', $url, $body);
    }

    public function patch(string $url, array $body = []): mixed
    {
        return $this->request('PATCH', $url, $body);
    }

    public function delete(string $url, array $body = []): mixed
    {
        return $this->request('DELETE', $url, $body);
    }

    /**
     * Indicate that the request is expecting a JSON response.
     */
    public function acceptJson(): self
    {
        $this->headers['Accept'] = 'application/json';
        return $this;
    }

    /**
     * Indicate the request body is JSON.
     */
    public function asJson(): self
    {
        $this->body_format = 'json';
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    /**
     * Indicate the request body is form-urlencoded.
     */
    public function asForm(): self
    {
        $this->body_format = 'form_params';
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this;
    }

    private function buildUri(string $url): Uri
    {
        $uri = new Uri($url);

        if($this->base_uri !== null) {
            $base = new Uri($this->base_uri);

            $uri = $uri->withScheme($base->getScheme());
            $uri = $uri->withHost($base->getHost());
            $uri = $uri->withPort($base->getPort());
            $uri = $uri->withPath(rtrim($base->getPath(), '/').'/'.ltrim($uri->getPath(), '/'));
        }

        return $uri;
    }

    protected function prepareBody(array $data): string|false
    {
        if (empty($data)) {
            return '';
        }

        if ($this->body_format === 'json') {
            return json_encode($data);
        }

        if ($this->body_format === 'form_params') {
            return http_build_query($data);
        }

        return '';
    }
}