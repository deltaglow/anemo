<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Enum\BodyFormat;
use DeltaGlow\Anemo\Exception\HttpException;
use DeltaGlow\Anemo\Response\Response;
use GuzzleHttp\Psr7\Uri;

abstract class BaseHttpClient extends BaseClient
{
    protected BodyFormat $body_format = BodyFormat::Text;

    abstract protected function doRequest(string $method, Uri $uri, string|array $body): Response;

    public function request(string $method, string $url, string|array $body = ''): mixed
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

    public function post(string $url, string|array $body = ''): mixed
    {
        return $this->request('POST', $url, $body);
    }

    public function put(string $url, string|array $body = ''): mixed
    {
        return $this->request('PUT', $url, $body);
    }

    public function patch(string $url, string|array $body = ''): mixed
    {
        return $this->request('PATCH', $url, $body);
    }

    public function delete(string $url, string|array $body = ''): mixed
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
        $this->body_format = BodyFormat::Json;
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    /**
     * Indicate the request body is form-urlencoded.
     */
    public function asForm(): self
    {
        $this->body_format = BodyFormat::FormParams;
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

    protected function prepareBody(string|array $data): string|false
    {
        if (empty($data)) {
            return '';
        }

        if($this->body_format === BodyFormat::Text) {
            if(is_array($data)) {
                $data = implode(PHP_EOL, $data);
            }
            return $data;
        } elseif ($this->body_format === BodyFormat::Json) {
            return json_encode($data);
        } elseif ($this->body_format === BodyFormat::FormParams) {
            if (!is_array($data) && !is_object($data)) {
                throw new HttpException('Form parameters must be an array or object.');
            }
            return http_build_query($data);
        }

        return $data;
    }
}