<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Enum\BodyFormat;
use DeltaGlow\Anemo\Exception\HttpException;
use DeltaGlow\Anemo\Response\Response;
use Uri\Rfc3986\Uri;

abstract class BaseHttpClient extends BaseClient
{
    protected BodyFormat $body_format = BodyFormat::Text;

    abstract protected function doRequest(string $method, Uri $uri, string|array $body): Response;

    public function request(string $method, string|Uri $uri, string|array $body = ''): mixed
    {
        $uri = $this->buildUri($uri);

        if ($this->pool) {
            $this->pool->addRequest($this->pool_key, function () use ($method, $uri, $body) {
                return $this->doRequest($method, $uri, $body);
            });
            return null;
        } else {
            return $this->doRequest($method, $uri, $body);
        }
    }

    public function get(string|Uri $url): mixed
    {
        return $this->request('GET', $url);
    }

    public function post(string|Uri $url, string|array $body = ''): mixed
    {
        return $this->request('POST', $url, $body);
    }

    public function put(string|Uri $url, string|array $body = ''): mixed
    {
        return $this->request('PUT', $url, $body);
    }

    public function patch(string|Uri $url, string|array $body = ''): mixed
    {
        return $this->request('PATCH', $url, $body);
    }

    public function delete(string|Uri $url, string|array $body = ''): mixed
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

    private function buildUri(string|Uri $uri): Uri
    {
        if(is_string($uri)) {
            if(str_starts_with($uri, '/')) {
                $uri = preg_replace('/^\/+/', '', $uri);
            }
            $uri = Uri::parse($uri);
            if($uri === null) {
                throw new \InvalidArgumentException(sprintf('Invalid url string "%s"', $this->options['base_uri']));
            }
        }

        if ($this->options['base_uri'] !== null && $uri->getHost() === null) {
            $uri = $uri->resolve($this->options['base_uri']);
        }

        return $uri;
    }

    protected function prepareBody(string|array $data): string|false
    {
        if (empty($data)) {
            return '';
        }

        if ($this->body_format === BodyFormat::Text) {
            if (is_array($data)) {
                $data = implode(PHP_EOL, $data);
            }
            return $data;
        } elseif ($this->body_format === BodyFormat::Json) {
            $json = json_encode($data);
            if ($json === false) {
                throw new HttpException('Failed to encode body as JSON: ' . json_last_error_msg());
            }
            return $json;
        } elseif ($this->body_format === BodyFormat::FormParams) {
            if (!is_array($data) && !is_object($data)) {
                throw new HttpException('Form parameters must be an array or object.');
            }
            return http_build_query($data);
        }

        return $data;
    }
}