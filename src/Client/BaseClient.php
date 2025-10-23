<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Pool;
use GuzzleHttp\Psr7\Uri;

abstract class BaseClient
{
    // Properties for pool requests
    protected string $method;
    protected ?string $base_uri = null;
    protected ?string $proxy_uri = null;
    protected ?string $http_proxy_host = null;
    protected ?int $http_proxy_port = null;
    protected ?string $http_proxy_user = null;
    protected ?string $http_proxy_password = null;
    protected int $timeout = 0;
    protected array $headers = [];
    protected array $cookies = [];
    protected bool $ssl_verify_peer = true;
    protected bool $ssl_allow_self_signed = false;
    protected ?string $ssl_cert_file = null;
    protected ?string $ssl_key_file = null;
    protected ?string $ssl_passphrase = null;
    protected ?string $ssl_cafile = null;
    protected ?string $ssl_capath = null;

    protected ?Pool $pool = null;
    protected ?string $pool_key = null;

    public function __construct(array $options = [])
    {
        foreach($options as $attribute => $value) {
            $this->{$attribute} = $value;
        }
    }

    public function setPool(Pool $pool, ?string $key = null): void
    {
        if($key === null) {
            $key = uniqid();
        }
        $this->pool = $pool;
        $this->pool_key = $key;
    }

    protected function buildPath(Uri $uri): Uri
    {
        $path = new Uri();
        $path = $path->withPath($uri->getPath())
            ->withQuery($uri->getQuery())
            ->withFragment($uri->getFragment());

        if($path->getPath() === '') {
            $path = $path->withPath('/');
        }

        return $path;
    }

    protected function buildSettings(): array
    {
        $settings = [
            'ssl_verify_peer' => $this->ssl_verify_peer,
            'ssl_allow_self_signed' => $this->ssl_allow_self_signed,
            'ssl_cert_file' => $this->ssl_cert_file,
            'ssl_key_file' => $this->ssl_key_file,
            'ssl_passphrase' => $this->ssl_passphrase,
            'ssl_cafile' => $this->ssl_cafile,
            'ssl_capath' => $this->ssl_capath,
            'timeout' => $this->timeout,
        ];

        if($this->proxy_uri !== null) {
            // format username:password@host:port
            $uri = new Uri($this->proxy_uri);
            if($uri->getUserInfo() !== null) {
                list($user, $pass) = explode(':', $uri->getUserInfo());
                $settings['http_proxy_user'] = $user;
                $settings['http_proxy_password'] = $pass;
            }
            $settings['http_proxy_host'] = $uri->getHost();
            $settings['http_proxy_port'] = $uri->getPort();
        } else {
            $settings['http_proxy_host'] = $this->http_proxy_host;
            $settings['http_proxy_user'] = $this->http_proxy_user;

            if($this->http_proxy_port !== null) {
                $settings['http_proxy_port'] = $this->http_proxy_port;
            }

            if($this->http_proxy_password !== null) {
                $settings['http_proxy_password'] = $this->http_proxy_password;
            }
        }

        return $settings;
    }

    /**
     * Set cookies to be sent with the request.
     * Note: This makes the PendingRequest instance stateful for subsequent requests.
     */
    public function withCookies(array $cookies): self
    {
        $this->cookies = array_merge($this->cookies, $cookies);
        return $this;
    }

    public function withCookie(string $name, string $value): self
    {
        $this->cookies[$name] = $value;
        return $this;
    }

    /**
     * Set the request headers.
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Add a bearer token to the request.
     */
    public function withBearerToken(string $token): self
    {
        $this->headers['Authorization'] = trim('Bearer ' . $token);
        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->headers['Authorization'] = trim('Basic ' . base64_encode($username . ':' . $password));
        return $this;
    }

    public function withApikey(string $token, string $type = 'Apikey'): self
    {
        $this->headers['Authorization'] = trim($type.' ' . $token);
        return $this;
    }
}